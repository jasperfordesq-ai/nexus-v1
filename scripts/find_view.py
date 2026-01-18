import re

filepath = "c:/Home Directory/source..sql"
try:
    with open(filepath, 'r', encoding='utf-8', errors='replace') as f:
        for i, line in enumerate(f):
            if "user_effective_permissions" in line and "VIEW" in line:
                print(f"Line {i+1}: {line.strip()}")
                # Print next few lines too as it might be multi-line
                try:
                    for _ in range(10):
                        print(next(f).strip())
                except StopIteration:
                    pass
except Exception as e:
    print(e)
