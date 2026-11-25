
from pathlib import Path
import pandas as pd

PROJECT_DIR = Path(__file__).resolve().parents[1]
DATA_DIR = PROJECT_DIR / "eea_data" / "e1a"
INVENTORY_FILE = PROJECT_DIR / "eea_inventory" / "eea_e1a_pm25_inventory.csv"
OUTPUT_DIR = PROJECT_DIR / "eea_reports"
OUTPUT_DIR.mkdir(exist_ok=True)

# Common reporting period: 2013-01-01 to 2026-01-01 (exclusive)
PERIOD_START = pd.Timestamp("2013-01-01")
PERIOD_END = pd.Timestamp("2026-01-01")

inventory = pd.read_csv(INVENTORY_FILE)
if any(inventory["dataset"] != "E1a"):
    raise ValueError(f"Analysis requires an E1a inventory: {INVENTORY_FILE}")
if any(inventory["pollutant"] != "PM2.5"):
    raise ValueError(f"Analysis requires PM2.5 records: {INVENTORY_FILE}")

summary_rows = []
yearly_rows = []

for _, item in inventory.iterrows():
    city = item["city"]
    filename = item["filename"]
    file_path = DATA_DIR / filename

    print(f"Analyzing {city}: {filename}")

    df = pd.read_parquet(file_path)
    all_records = len(df)
    verification_counts = df["Verification"].value_counts(dropna=False).to_dict()

    df["Start"] = pd.to_datetime(df["Start"], errors="coerce")
    df["Value"] = pd.to_numeric(df["Value"], errors="coerce")

    if not df["AggType"].eq("day").all():
        raise ValueError(f"Expected daily E1a records in {file_path}")
    if not df["Pollutant"].astype(str).eq("6001").all():
        raise ValueError(f"Expected PM2.5 pollutant code 6001 in {file_path}")

    # Keep only records with usable dates and numeric values.
    df = df.dropna(subset=["Start", "Value"]).copy()

    # The documented main-analysis rule filters validity. Keep verification
    # available for reporting rather than silently adding an undocumented rule.
    valid = df[df["Validity"] == 1].copy()

    # Daily data: use the date part of Start.
    valid["date"] = valid["Start"].dt.normalize()
    valid = valid[
        (valid["date"] >= PERIOD_START)
        & (valid["date"] < PERIOD_END)
    ]

    available_days = valid["date"].nunique()
    expected_days = len(
        pd.date_range(
            PERIOD_START,
            PERIOD_END - pd.Timedelta(days=1),
            freq="D",
        )
    )

    coverage = (
        available_days / expected_days * 100
        if expected_days else 0
    )

    summary_rows.append({
        "city": city,
        "sampling_point": filename.replace(".parquet", ""),
        "all_records": all_records,
        "validity_1_records_in_period": len(valid),
        "verification_values": str(verification_counts),
        "first_measurement": df["Start"].min(),
        "last_measurement": df["Start"].max(),
        "available_days": available_days,
        "expected_days_2013_2025": expected_days,
        "coverage_percent_2013_2025": round(coverage, 2),
        "mean_pm25": round(valid["Value"].mean(), 2)
            if not valid.empty else None,
        "median_pm25": round(valid["Value"].median(), 2)
            if not valid.empty else None,
        "max_pm25": round(valid["Value"].max(), 2)
            if not valid.empty else None,
        "unit": ", ".join(
            df["Unit"].dropna().astype(str).unique()
        ),
    })

    # Yearly coverage and statistics.
    valid["year"] = valid["date"].dt.year

    for year in range(2013, 2026):
        year_start = pd.Timestamp(f"{year}-01-01")
        year_end = pd.Timestamp(f"{year + 1}-01-01")

        year_data = valid[
            (valid["date"] >= year_start)
            & (valid["date"] < year_end)
        ]

        expected = (
            year_end - year_start
        ).days

        available = year_data["date"].nunique()

        yearly_rows.append({
            "city": city,
            "sampling_point": filename.replace(".parquet", ""),
            "year": year,
            "available_days": available,
            "expected_days": expected,
            "coverage_percent": round(
                available / expected * 100, 2
            ),
            "mean_pm25": round(year_data["Value"].mean(), 2)
                if not year_data.empty else None,
            "median_pm25": round(year_data["Value"].median(), 2)
                if not year_data.empty else None,
        })


summary = pd.DataFrame(summary_rows)
yearly = pd.DataFrame(yearly_rows)

summary.to_csv(
    OUTPUT_DIR / "sampling_points_summary.csv",
    index=False,
)

yearly.to_csv(
    OUTPUT_DIR / "yearly_coverage.csv",
    index=False,
)

print("\n=== SAMPLING POINT SUMMARY ===")
print(summary.to_string(index=False))

print("\n=== 2025 COVERAGE ===")
print(
    yearly[yearly["year"] == 2025][
        ["city", "sampling_point", "available_days",
         "expected_days", "coverage_percent", "mean_pm25"]
    ].to_string(index=False)
)

print("\nReports saved in:", OUTPUT_DIR)

