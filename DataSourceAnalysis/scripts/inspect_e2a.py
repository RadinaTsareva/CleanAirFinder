
import pandas as pd
from pathlib import Path

PROJECT_DIR = Path(__file__).resolve().parents[1]

files = [
    "sofia-pm25-e2a-1.parquet",
    "sofia-pm25-e2a-2.parquet"
]

for file in files:
    file = PROJECT_DIR / file
    df = pd.read_parquet(file)

    print(f"\n{'=' * 50}")
    print(f"FILE: {file}")
    print(f"{'=' * 50}")

    print("\nSHAPE:")
    print(df.shape)

    print("\nDATE RANGE:")
    print("Start:", df["Start"].min())
    print("End:", df["End"].max())

    print("\nAGGREGATION:")
    print(df["AggType"].value_counts())

    print("\nVERIFICATION:")
    print(df["Verification"].value_counts())

    print("\nVALIDITY:")
    print(df["Validity"].value_counts())

    print("\nUNITS:")
    print(df["Unit"].value_counts())

    print("\nLAST 10 RECORDS:")
    print(df.tail(10).to_string(index=False))


import pandas as pd

files = {
    "Station 1": "sofia-pm25-e2a-1.parquet",
    "Station 2": "sofia-pm25-e2a-2.parquet"
}

for station, file in files.items():
    file = PROJECT_DIR / file
    df = pd.read_parquet(file)

    df["Value"] = pd.to_numeric(df["Value"], errors="coerce")

    print(f"\n--- {station} ---")

    print("\nVALIDITY / VERIFICATION:")
    print(pd.crosstab(
        df["Validity"],
        df["Verification"],
        margins=True
    ))

    print("\nDUPLICATE START TIMES:")
    print(df.duplicated(
        subset=["Samplingpoint", "Start", "End"]
    ).sum())

    # Exploratory only: use the recorded Start date as-is; timezone handling
    # remains unresolved, as documented for E2a in DATA_SOURCE_ANALYSIS.md.
    valid = df[df["Validity"] == 1].copy()

    # Daily statistics from hourly observations
    daily = valid.groupby(
        valid["Start"].dt.date
    ).agg(
        average=("Value", "mean"),
        valid_hours=("Value", "count"),
        minimum=("Value", "min"),
        maximum=("Value", "max")
    )

    print("\nDAILY COVERAGE:")
    print(daily["valid_hours"].describe())

    print("\nDAYS WITH FEWER THAN 18 VALID HOURS:")
    print((daily["valid_hours"] < 18).sum())

    print("\nLAST 10 DAYS:")
    print(daily.tail(10).to_string())


import pandas as pd

files = {
    "Station 1": "sofia-pm25-e2a-1.parquet",
    "Station 2": "sofia-pm25-e2a-2.parquet"
}

# Exploratory threshold only: 18 valid hourly values per recorded calendar
# day. This is not an EEA rule or a finalized project eligibility threshold.
# Dates use the source Start timestamps without timezone conversion.
start_date = "2026-01-01"
end_date = "2026-11-25"  # exclusive

calendar = pd.date_range(
    start=start_date,
    end=pd.Timestamp(end_date) - pd.Timedelta(days=1),
    freq="D"
)

for station, file in files.items():
    file = PROJECT_DIR / file
    df = pd.read_parquet(file)

    df["Value"] = pd.to_numeric(
        df["Value"], errors="coerce"
    )

    valid = df[df["Validity"] == 1].copy()

    valid["date"] = valid["Start"].dt.normalize()

    daily = valid.groupby("date").agg(
        average=("Value", "mean"),
        valid_hours=("Value", "count")
    )

    # Include days without any valid measurements.
    daily = daily.reindex(calendar)

    daily["valid_hours"] = (
        daily["valid_hours"].fillna(0)
    )

    daily["is_eligible"] = (
        daily["valid_hours"] >= 18
    )

    print(f"\n--- {station} ---")
    print("Expected days:", len(calendar))
    print("Days with measurements:",
          (daily["valid_hours"] > 0).sum())
    print("Eligible days:",
          daily["is_eligible"].sum())
    print("Missing days:",
          (daily["valid_hours"] == 0).sum())
    print("Days below 18 hours:",
          ((daily["valid_hours"] > 0) &
           (daily["valid_hours"] < 18)).sum())

    print("Coverage:",
          round(
              daily["is_eligible"].sum()
              / len(calendar) * 100, 2
          ), "%")

    print("\nMONTHLY COVERAGE:")

    monthly = daily.groupby(daily.index.month).agg(
        expected_days=("valid_hours", "size"),
        eligible_days=("is_eligible", "sum")
    )

    monthly["coverage"] = (
        monthly["eligible_days"]
        / monthly["expected_days"] * 100
    )

    print(monthly.round(2))

