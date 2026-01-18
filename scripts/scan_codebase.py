import os
import re

def scan_file(filepath):
    tables = set()
    try:
        with open(filepath, 'r', encoding='utf-8', errors='replace') as f:
            content = f.read()
            
            # Pattern 1: INSERT INTO, UPDATE, FROM, JOIN
            # We need to be careful not to match variables like FROM $table
            # `?(\w+)`? matches `table` or table
            
            sql_patterns = [
                r"INSERT\s+INTO\s+`?([a-zA-Z0-9_]+)`?",
                r"UPDATE\s+`?([a-zA-Z0-9_]+)`?\s+SET",
                r"FROM\s+`?([a-zA-Z0-9_]+)`?",
                r"JOIN\s+`?([a-zA-Z0-9_]+)`?",
                r"CASE\s+`?([a-zA-Z0-9_]+)`?",  # simplistic
            ]
            
            for p in sql_patterns:
                matches = re.findall(p, content, re.IGNORECASE)
                for m in matches:
                    # Filter out non-table keywords
                    # Heuristic: Tables are usually lowercase snake_case
                    if not m.startswith('$') and m.lower() not in ignore_list and not any(c.isupper() for c in m):
                        tables.add(m)
            
            # Pattern 2: Model definitions if any
            # protected $table = 'users';
            model_match = re.search(r"protected\s+\$table\s*=\s*['\"](\w+)['\"]", content)
            if model_match:
                tables.add(model_match.group(1))
                
    except Exception as e:
        pass
        
    return tables

def main():
    root_dirs = ['src', 'views', 'public']
    all_tables = set()
    
    print("Scanning codebase...")
    for d in root_dirs:
        if not os.path.exists(d):
            continue
            
        for root, dirs, files in os.walk(d):
            for file in files:
                if file.endswith('.php'):
                    path = os.path.join(root, file)
                    found = scan_file(path)
                    all_tables.update(found)
                    
    print("\n--- CODEBASE TABLES ---")
    for t in sorted(all_tables):
        print(t)

if __name__ == "__main__":
    main()
