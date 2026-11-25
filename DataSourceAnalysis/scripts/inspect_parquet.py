
import pandas as pd
from pathlib import Path

PROJECT_DIR = Path(__file__).resolve().parents[1]

def load_qualified_daily(path):
    df = pd.read_parquet(path)
    df["Value"] = pd.to_numeric(df["Value"], errors="coerce")
    df = df[
        (df["Pollutant"].astype(str) == "6001")
        & (df["AggType"] == "day")
        & (df["Validity"] == 1)
    ].dropna(subset=["Start", "Value"])
    if df["Start"].duplicated().any():
        raise ValueError(f"Duplicate daily date keys found in {path}")
    return df


df1 = load_qualified_daily(PROJECT_DIR / "sofia-pm25.parquet")
df2 = load_qualified_daily(PROJECT_DIR / "sofia-pm25-2.parquet")

# Compare only dates available in both datasets
comparison = pd.merge(
    df1[["Start", "Value"]].rename(
        columns={"Value": "station_1"}
    ),
    df2[["Start", "Value"]].rename(
        columns={"Value": "station_2"}
    ),
    on="Start",
    how="inner"
)

print("\n--- OVERLAPPING DATES ---")
print("Common days:", len(comparison))

print("\n--- COMPARISON ---")
print(comparison[["station_1", "station_2"]].describe())

print("\n--- CORRELATION ---")
print(
    comparison["station_1"].corr(
        comparison["station_2"]
    )
)

print("\n--- AVERAGE ON SAME DAYS ---")
print("Station 1:", comparison["station_1"].mean())
print("Station 2:", comparison["station_2"].mean())

print("\n--- DAILY DIFFERENCE ---")
comparison["difference"] = (
    comparison["station_1"] - comparison["station_2"]
)

print(comparison["difference"].describe())

print("\n--- 2025 COMPARISON ---")
comparison_2025 = comparison[
    (comparison["Start"] >= "2025-01-01") &
    (comparison["Start"] < "2026-01-01")
]

print("Common days in 2025:", len(comparison_2025))
print(comparison_2025[["station_1", "station_2"]].mean())


df1["year"] = df1["Start"].dt.year
df2["year"] = df2["Start"].dt.year

print("\n--- STATION 1: YEARLY STATISTICS ---")
print(
    df1.groupby("year")["Value"]
    .agg(["count", "mean", "median", "max"])
    .round(2)
)

print("\n--- STATION 2: YEARLY STATISTICS ---")
print(
    df2.groupby("year")["Value"]
    .agg(["count", "mean", "median", "max"])
    .round(2)
)

