import re

def main():
    try:
        with open('wed-3.sql', 'r', encoding='utf-8', errors='replace') as f:
            sql = f.read()
    except:
        return

    tables = ['transactions', 'user_blocks', 'match_preferences']
    for t in tables:
        print(f"--- {t} ---")
        pattern = r"CREATE TABLE\s+(?:IF NOT EXISTS\s+)?`?" + re.escape(t) + r"`?\s*\((.*?)\)\s*ENGINE"
        match = re.search(pattern, sql, re.DOTALL | re.IGNORECASE)
        if match:
            print(match.group(1)[:500])
        else:
            print("NOT FOUND")

if __name__ == '__main__':
    main()
