
import csv
import urllib.request
from pathlib import Path

PROJECT_DIR = Path(__file__).resolve().parents[1]
INPUT_FILE = PROJECT_DIR / "eea_inventory" / "eea_e1a_pm25_inventory.csv"
OUTPUT_DIR = PROJECT_DIR / "eea_data" / "e1a"
OUTPUT_DIR.mkdir(parents=True, exist_ok=True)

with INPUT_FILE.open("r", encoding="utf-8") as file:
    rows = list(csv.DictReader(file))

if any(row.get("dataset") != "E1a" for row in rows):
    raise ValueError(f"Inventory must contain only E1a records: {INPUT_FILE}")

downloaded = 0

for index, row in enumerate(rows, start=1):
    filename = row["filename"]
    url = row["url"]
    destination = OUTPUT_DIR / filename

    if destination.exists():
        print(f"[{index}/{len(rows)}] Already exists: {filename}")
        continue

    print(f"[{index}/{len(rows)}] Downloading {row['city']}: {filename}")

    try:
        request = urllib.request.Request(
            url,
            headers={"User-Agent": "CleanAirFinder/1.0"},
        )

        with urllib.request.urlopen(request, timeout=180) as response:
            destination.write_bytes(response.read())

        downloaded += 1
        print(f"    Saved: {destination}")

    except Exception as error:
        print(f"    ERROR: {error}")

print("\nFinished!")
print(f"New files downloaded: {downloaded}")
print(f"Total files in inventory: {len(rows)}")

