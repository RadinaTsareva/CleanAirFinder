
import csv
import re
import time
import urllib.request
import urllib.error
from pathlib import Path

API_URL = (
    "https://eeadmz1-downloads-api-appservice.azurewebsites.net"
    "/ParquetFile/urls"
)

CITIES = [
    "Varna",
    "Pleven",
    "Veliko Tarnovo",
    "Sofia",
    "Burgas",
    "Ruse",
    "Pazardzhik",
    "Plovdiv",
    "Stara Zagora",
    "Blagoevgrad",
    "Pernik",
    "Vidin",
    "Shumen",
    "Vratsa",
    "Haskovo",
    "Dobrich",
    "Sliven",
]

PROJECT_DIR = Path(__file__).resolve().parents[1]
OUTPUT_DIR = PROJECT_DIR / "eea_inventory"
OUTPUT_DIR.mkdir(exist_ok=True)

DATASETS = {
    "E2a": 1,
    "E1a": 2,
}


def get_urls(city, dataset_id):
    payload = {
        "countries": ["BG"],
        "cities": [city],
        "pollutants": ["PM2.5"],
        "dataset": dataset_id,
        "source": "API",
        "method": None,
        "email": None,
        "compress": True,
    }

    import json

    request = urllib.request.Request(
        API_URL,
        data=json.dumps(payload).encode("utf-8"),
        headers={
            "accept": "text/plain",
            "Content-Type": "application/json",
        },
        method="POST",
    )

    with urllib.request.urlopen(request, timeout=120) as response:
        content = response.read().decode("utf-8", errors="replace")

    # Extract URLs regardless of whether the response is CSV or plain text.
    return sorted(set(re.findall(r"https?://[^\s,\"']+", content)))


for dataset_name, dataset_id in DATASETS.items():
    rows = []

    for index, city in enumerate(CITIES, start=1):
        print(f"[{dataset_name} {index}/{len(CITIES)}] Checking {city}...")

        try:
            urls = get_urls(city, dataset_id)
            print(f"    Found {len(urls)} files")

            for url in urls:
                filename = url.split("/")[-1].split("?")[0]
                sampling_point = filename.removesuffix(".parquet")

                rows.append({
                    "city": city,
                    "sampling_point": sampling_point,
                    "filename": filename,
                    "url": url,
                    "dataset": dataset_name,
                    "pollutant": "PM2.5",
                })

        except urllib.error.HTTPError as error:
            print(f"    HTTP error: {error.code} - {error.reason}")
        except Exception as error:
            print(f"    Error: {error}")

        time.sleep(0.5)

    output_file = OUTPUT_DIR / f"eea_{dataset_name.lower()}_pm25_inventory.csv"
    with output_file.open("w", newline="", encoding="utf-8") as file:
        writer = csv.DictWriter(
            file,
            fieldnames=["city", "sampling_point", "filename", "url", "dataset", "pollutant"],
        )
        writer.writeheader()
        writer.writerows(rows)

    print(f"\n{dataset_name} finished: {len(rows)} files across "
          f"{len(set(row['city'] for row in rows))} cities")
    print(f"Report: {output_file}")

