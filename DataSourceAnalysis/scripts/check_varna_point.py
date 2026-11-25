
import pandas as pd
from pathlib import Path

file_path = Path(__file__).resolve().parents[1] / "eea_data/e1a/SPO-BG0075A_06001_100.parquet"

df = pd.read_parquet(file_path)

print("Columns:")
print(df.columns.tolist())

print("\nSampling points:")
print(df["Samplingpoint"].value_counts().to_string())

print("\nPollutants:")
print(df["Pollutant"].value_counts().to_string())

print("\nDate range:")
print(df["Start"].min(), "->", df["Start"].max())

print("\nFirst 5 records:")
print(df.head().to_string(index=False))

