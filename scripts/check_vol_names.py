import re

def main():
    try:
        with open('wed-3.sql', 'r', encoding='utf-8', errors='replace') as f:
            sql = f.read()
    except:
        return

    tables = ['vol_applications', 'volunteer_applications', 'vol_hours', 'volunteer_hours', 'vol_logs', 'vol_log', 'vol_organizations']
    for t in tables:
        print(f"--- {t} ---")
        pattern = r"CREATE TABLE\s+(?:IF NOT EXISTS\s+)?`?" + re.escape(t) + r"`?\s*\((.*?)\)\s*ENGINE"
        match = re.search(pattern, sql, re.DOTALL | re.IGNORECASE)
        if match:
            print(match.group(1)[:200]) # First 200 chars
        else:
            print("NOT FOUND")

if __name__ == '__main__':
    main()
