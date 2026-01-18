import os
import re

files_to_scan = [
    'src/Controllers/Admin/EnterpriseController.php',
    'src/Controllers/Admin/GamificationController.php',
    'src/Controllers/Admin/GroupAdminController.php',
    'src/Controllers/Admin/VolunteeringController.php'
]

patterns = [
    r'volunteer_\w+',
    r'user_connections',
    r'user_blocks'
]

base = 'c:/xampp/htdocs/staging/'

for frel in files_to_scan:
    path = os.path.join(base, frel)
    if not os.path.exists(path):
        print(f"Skipping {frel} (Not found)")
        continue
        
    print(f"--- Scanning {frel} ---")
    try:
        with open(path, 'r', encoding='utf-8') as f:
            content = f.read()
            for p in patterns:
                matches = re.finditer(p, content)
                for m in matches:
                    # Context
                    start = max(0, m.start() - 30)
                    end = min(len(content), m.end() + 30)
                    print(f"MATCH {m.group(0)}: ...{content[start:end].replace(chr(10), ' ')}...")
    except Exception as e:
        print(f"Error: {e}")
