
import pandas as pd
from pathlib import Path

PROJECT_DIR = Path(__file__).resolve().parents[1]
INPUT_FILE = PROJECT_DIR / "eea_inventory" / "metadata.csv"
OUTPUT_FILE = PROJECT_DIR / "eea_inventory" / "station_metadata_bg_pm25.csv"

TARGET_POINTS = [
    "SPO-BG0075A_06001_100",
    "SPO-BG0020A_06001_100",
    "SPO-BG0050A_06001_100",
    "SPO-BG0070A_06001_100",
    "SPO-BG0045A_06001_100",
    "SPO-BG0051A_06001_100",
    "SPO-BG0039A_06001_100",
    "SPO-BG0046A_06001_100",
]

print("Reading metadata file...")

df = pd.read_csv(
    INPUT_FILE,
    sep="\t",
    low_memory=False,
)

# Filter Bulgarian PM2.5 sampling points.
filtered = df[
    (df["Countrycode"] == "BG")
    & (df["AirPollutant"] == "PM2.5")
    & (df["SamplingPoint"].isin(TARGET_POINTS))
].copy()

columns = [
    "Countrycode",
    "Timezone",
    "AirQualityNetwork",
    "AirQualityStation",
    "AirQualityStationEoICode",
    "AirQualityStationNatCode",
    "SamplingPoint",
    "AirPollutant",
    "Longitude",
    "Latitude",
    "Altitude",
    "MeasurementType",
    "MeasurementMethod",
    "AirQualityStationType",
    "AirQualityStationArea",
    "MeasurementEquipment",
    "InletHeight",
    "BuildingDistance",
    "KerbDistance",
]

result = filtered[columns].drop_duplicates()

result.to_csv(OUTPUT_FILE, index=False)

print("\n=== MATCHED STATIONS ===")
print(result.to_string(index=False))

print("\n=== SUMMARY ===")
print("Matched rows:", len(result))
print("Matched sampling points:", result["SamplingPoint"].nunique())
print("Expected sampling points:", len(TARGET_POINTS))

missing = set(TARGET_POINTS) - set(result["SamplingPoint"])

print("\nMissing sampling points:")
for point in sorted(missing):
    print("-", point)

print("\nSaved:", OUTPUT_FILE)

