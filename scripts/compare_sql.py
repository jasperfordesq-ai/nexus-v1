import re
import os

def parse_sql(filepath):
    """
    Parses a SQL dump file and returns a dictionary of tables and their column definitions.
    Returns: { 'table_name': { 'columns': { 'col_name': 'full_definition' }, 'indexes': [] } }
    """
    schema = {}
    current_table = None
    
    print(f"Parsing {filepath}...")
    try:
        with open(filepath, 'r', encoding='utf-8', errors='replace') as f:
            for line in f:
                line = line.strip()
                
                # Match CREATE TABLE statement
                create_match = re.match(r'^CREATE TABLE\s+`?(\w+)`?', line, re.IGNORECASE)
                if create_match:
                    current_table = create_match.group(1)
                    schema[current_table] = {'columns': {}, 'lines': []}
                    continue
                
                # If we are inside a table definition
                if current_table:
                    if line.startswith(') ENGINE='):
                        current_table = None
                        continue
                    
                    # Store column/key definitions
                    # Simple heuristic: lines starting with ` are usually columns or keys
                    if line.startswith('`'):
                        # Check if it's a key
                        if line.startswith('`') and ('KEY' in line or 'PRIMARY' in line or 'UNIQUE' in line or 'CONSTRAINT' in line):
                             schema[current_table]['lines'].append(line.rstrip(','))
                        else:
                            # It's likely a column
                            # Extract column name
                            col_match = re.match(r'^`(\w+)`\s+(.*),?$', line)
                            if col_match:
                                col_name = col_match.group(1)
                                col_def = col_match.group(2).rstrip(',')
                                schema[current_table]['columns'][col_name] = col_def
                                schema[current_table]['lines'].append(line.rstrip(','))
                    elif line.startswith('PRIMARY KEY') or line.startswith('UNIQUE KEY') or line.startswith('KEY') or line.startswith('CONSTRAINT'):
                         schema[current_table]['lines'].append(line.rstrip(','))

    except Exception as e:
        print(f"Error parsing {filepath}: {e}")
        
    return schema

def compare_schemas(source_path, dest_path):
    source_schema = parse_sql(source_path)
    dest_schema = parse_sql(dest_path)
    
    missing_tables = []
    missing_columns = []
    type_mismatches = []
    
    # Check for missing tables in destination
    for table in source_schema:
        if table not in dest_schema:
            missing_tables.append(table)
        else:
            # Check for missing columns
            for col, defn in source_schema[table]['columns'].items():
                if col not in dest_schema[table]['columns']:
                    missing_columns.append(f"{table}.{col} ({defn})")
                else:
                    # Optional: Compare types/definitions
                    dest_defn = dest_schema[table]['columns'][col]
                    # Normalize for simple comparison (ignore charset diffs if needed, but reporting them is good)
                    if defn != dest_defn:
                        type_mismatches.append(f"{table}.{col}\n  Source: {defn}\n  Dest:   {dest_defn}")

    print("\n--- COMPARISON REPORT ---")
    
    if missing_tables:
        print("\n[MISSING TABLES] (Present in Source, missing in Destination):")
        for t in missing_tables:
            print(f"- {t}")
    else:
        print("\n[MISSING TABLES]: None")
        
    if missing_columns:
        print("\n[MISSING COLUMNS] (Present in Source, missing in Destination):")
        for c in missing_columns:
            print(f"- {c}")
    else:
        print("\n[MISSING COLUMNS]: None")
        
    if type_mismatches:
        print("\n[COLUMN DEFINITION MISMATCHES] (Likely updates needed):")
        # Limit output if too many
        count = 0
        for m in type_mismatches:
            if count < 50:
                print(f"- {m}")
            elif count == 50:
                print(f"... and {len(type_mismatches) - 50} more mismatches.")
            count += 1
            
    # Also check for tables present in Destination but not Source (Deprecations)
    extra_tables = []
    for table in dest_schema:
        if table not in source_schema:
            extra_tables.append(table)
            
    if extra_tables:
        print("\n[EXTRA TABLES] (Present in Destination, missing in Source - Candidates for removal?):")
        for t in extra_tables:
            print(f"- {t}")

if __name__ == "__main__":
    source = "c:/Home Directory/source..sql"
    dest = "c:/Home Directory/destination-3.sql"
    
    if not os.path.exists(source):
        print(f"File not found: {source}")
        # Try without double dot if it was a typo in my manual check?
        if os.path.exists("c:/Home Directory/source.sql"):
             source = "c:/Home Directory/source.sql"
    
    compare_schemas(source, dest)
