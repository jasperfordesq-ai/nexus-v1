import re
import os

def get_sql_tables(filepath):
    tables = set()
    if not os.path.exists(filepath):
        return tables
    with open(filepath, 'r', encoding='utf-8', errors='replace') as f:
        for line in f:
            match = re.search(r'CREATE TABLE\s+(?:IF NOT EXISTS\s+)?`?(\w+)`?', line, re.IGNORECASE)
            if match:
                tables.add(match.group(1))
    return tables

def get_php_schema_tables(filepath):
    tables = set()
    if not os.path.exists(filepath):
        return tables
    with open(filepath, 'r', encoding='utf-8', errors='replace') as f:
        content = f.read()
    in_schema = False
    for line in content.splitlines():
        if '$requiredSchema = [' in line:
            in_schema = True
            continue
        if in_schema and '];' in line:
            break
        if in_schema:
            match = re.match(r"\s*'(\w+)'\s*=>", line)
            if match:
                tables.add(match.group(1))
    return tables

def scan_codebase_tables(root_dirs):
    tables = set()
    sql_patterns = [
        r"INSERT\s+INTO\s+`?([a-zA-Z0-9_]+)`?",
        r"UPDATE\s+`?([a-zA-Z0-9_]+)`?\s+SET",
        r"FROM\s+`?([a-zA-Z0-9_]+)`?",
        r"JOIN\s+`?([a-zA-Z0-9_]+)`?",
    ]
    
    ignore_list = {
        'select', 'where', 'order', 'group', 'limit', 'left', 'right', 'inner', 'outer', 'set', 'values', 'as', 'on', 'and', 'or', 'case', 'when', 'then', 'else', 'end', 'update', 'insert', 'into', 'delete', 'from', 'join', 'union', 'all', 'distinct', 'having', 'like', 'in', 'is', 'not', 'null', 'true', 'false', 'unknown', 'exists', 'between', 'create', 'table', 'database', 'view', 'trigger', 'procedure', 'function', 'index', 'primary', 'key', 'foreign', 'constraint', 'references', 'check', 'default', 'auto_increment', 'unsigned', 'signed', 'int', 'varchar', 'text', 'date', 'datetime', 'timestamp', 'bool', 'boolean', 'blob', 'json',
        'get', 'post', 'request', 'response', 'session', 'cookie', 'server', 'env', 'config', 'log', 'cache', 'queue', 'db', 'auth', 'user', 'app', 'api', 'url', 'route', 'view', 'controller', 'model', 'middleware', 'provider', 'console', 'scheduler', 'job', 'event', 'listener', 'notification', 'mail', 'broadcast', 'storage', 'file', 'image', 'video', 'audio', 'document', 'pdf', 'csv', 'excel', 'word', 'powerpoint', 'zip', 'tar', 'ball', 'xml', 'html', 'css', 'js', 'json', 'yaml', 'yml', 'md', 'txt',
        '0', '1', '2', '2026', 'null', 'true', 'false', 'unknown', 'array', 'string', 'int', 'float', 'bool', 'object', 'resource', 'callable', 'iterable', 'void', 'never', 'mixed', 'this', 'self', 'parent', 'static', 'class', 'trait', 'interface', 'extends', 'implements', 'use', 'namespace', 'public', 'protected', 'private', 'final', 'abstract', 'const', 'var', 'function', 'return', 'if', 'else', 'elseif', 'endif', 'while', 'endwhile', 'do', 'for', 'endfor', 'foreach', 'endforeach', 'switch', 'endswitch', 'case', 'default', 'break', 'continue', 'try', 'catch', 'finally', 'throw', 'new', 'clone', 'instanceof', 'echo', 'print', 'isset', 'unset', 'empty', 'die', 'exit', 'eval', 'include', 'include_once', 'require', 'require_once', 'list', 'global',
        'api', 'ai', 'db', 'ui', 'xp'
    }

    for d in root_dirs:
        if not os.path.exists(d):
            continue
        for root, dirs, files in os.walk(d):
            for file in files:
                if file.endswith('.php'):
                    try:
                        with open(os.path.join(root, file), 'r', encoding='utf-8', errors='replace') as f:
                            content = f.read()
                            for p in sql_patterns:
                                matches = re.findall(p, content, re.IGNORECASE)
                                for m in matches:
                                    if not m.startswith('$') and m.lower() not in ignore_list and len(m) > 2 and not any(c.isupper() for c in m):
                                        tables.add(m)
                    except: pass
    return tables

def main():
    sql_tables = get_sql_tables('wed-3.sql')
    php_schema_tables = get_php_schema_tables('scripts/check_database_schema.php')
    codebase_tables = scan_codebase_tables(['src', 'views', 'public'])
    
    needed_tables = php_schema_tables.union(codebase_tables)
    missing_tables = needed_tables - sql_tables
    
    # Filter missing tables to remove likely false positives (uppercase, common words)
    filtered_missing = []
    for t in missing_tables:
        t_clean = t.strip('`').strip("'").strip('"')
        if t_clean in sql_tables: continue # Handle quoting diffs
        # Heuristic: mostly lowercase, underscores, not uppercase keywords
        if t_clean.isupper(): continue
        if len(t_clean) < 3: continue
        filtered_missing.append(t_clean)
        
    print("--- MISSING TABLES (Required by code, not in SQL) ---")
    for t in sorted(filtered_missing):
        print(t)

if __name__ == "__main__":
    main()
