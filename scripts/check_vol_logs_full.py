import re

def main():
    try:
        with open('wed-3.sql', 'r', encoding='utf-8', errors='replace') as f:
            sql = f.read()
    except:
        return

    # Check vol_logs full
    match = re.search(r"CREATE TABLE\s+(?:IF NOT EXISTS\s+)?`?vol_logs`?\s*\((.*?)\)\s*ENGINE", sql, re.DOTALL | re.IGNORECASE)
    if match:
        print(f"--- vol_logs ---\n{match.group(1)}")
    else:
        print("vol_logs NOT FOUND")

if __name__ == '__main__':
    main()
