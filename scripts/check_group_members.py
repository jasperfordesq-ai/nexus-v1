import re

def main():
    try:
        with open('wed-3.sql', 'r', encoding='utf-8', errors='replace') as f:
            sql = f.read()
    except:
        return

    t = 'group_members'
    print(f"--- {t} ---")
    pattern = r"CREATE TABLE\s+(?:IF NOT EXISTS\s+)?`?" + re.escape(t) + r"`?\s*\((.*?)\)\s*ENGINE"
    match = re.search(pattern, sql, re.DOTALL | re.IGNORECASE)
    if match:
        print(match.group(1)[:500])
    else:
        print("NOT FOUND")

if __name__ == '__main__':
    main()
