import re
import os

def get_sql_tables(filepath):
    tables = []
    if not os.path.exists(filepath):
        print(f"File not found: {filepath}")
        return tables
    
    with open(filepath, 'r', encoding='utf-8', errors='replace') as f:
        for line in f:
            match = re.search(r'CREATE TABLE\s+`?(\w+)`?', line, re.IGNORECASE)
            if match:
                tables.append(match.group(1))
    return tables

def get_php_schema_tables(filepath):
    tables = []
    if not os.path.exists(filepath):
        print(f"File not found: {filepath}")
        return tables

    with open(filepath, 'r', encoding='utf-8', errors='replace') as f:
        content = f.read()
        
    # Look for $requiredSchema = [ ... ];
    # This is a bit complex with regex, but we can look for array keys inside the variable definition
    # or just simple heuristic: lines like 'tablename' => [
    
    # Let's use a simple line-by-line heuristic as it's cleaner than a massive regex
    in_schema = False
    for line in content.splitlines():
        if '$requiredSchema = [' in line:
            in_schema = True
            continue
        if in_schema and '];' in line:
            in_schema = False
            break
        
        if in_schema:
            # Match 'tablename' => [
            match = re.match(r"\s*'(\w+)'\s*=>", line)
            if match:
                tables.append(match.group(1))
                
    return tables

def main():
    sql_tables = get_sql_tables('wed-3.sql')
    php_tables = get_php_schema_tables('scripts/check_database_schema.php')
    
    print("--- SQL DUMP TABLES ---")
    for t in sorted(sql_tables):
        print(t)
        
    print("\n--- PHP SCHEMA TABLES ---")
    for t in sorted(php_tables):
        print(t)

if __name__ == "__main__":
    main()
