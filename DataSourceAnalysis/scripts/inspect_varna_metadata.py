
import pandas as pd
from pathlib import Path

PROJECT_DIR = Path(__file__).resolve().parents[1]

df = pd.read_csv(
    PROJECT_DIR / "eea_inventory/metadata.csv",
    sep="\t",
    low_memory=False,
)

varna = df[
    (df["Countrycode"] == "BG")
    & (
        df["AirQualityStationEoICode"].astype(str).str.contains(
            "BG0075A", na=False
        )
        | df["AirQualityStationNatCode"].astype(str).str.contains(
            "BG0075A", na=False
        )
        | df["SamplingPoint"].astype(str).str.contains(
            "BG0075A", na=False
        )
    )
]

columns = [
    "AirQualityStationEoICode",
    "AirQualityStationNatCode",
    "AirQualityStation",
    "SamplingPoint",
    "AirPollutant",
    "Longitude",
    "Latitude",
    "AirQualityStationType",
    "AirQualityStationArea",
]

print(varna[columns].drop_duplicates().to_string(index=False))
print("\nTotal rows:", len(varna))

