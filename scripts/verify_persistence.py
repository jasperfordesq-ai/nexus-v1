import os

files = {
    'src/Services/SmartMatchingEngine.php': [
        'sender_id', 
        'user_id = ? AND tenant_id = ?', 
        'user_blocks'
    ],
    'src/Services/GroupAchievementService.php': [
        "status = 'active'", 
        "vol_logs"
    ],
    'src/Services/Enterprise/GdprService.php': [
        "vol_opportunities", 
        "vol_logs"
    ],
    'src/Services/SocialGamificationService.php': [
        "connections uc"
    ],
    'src/Controllers/AdminController.php': [
        "vol_organizations",
        "vol_opportunities",
        "vol_applications"
    ]
}

base_path = 'c:/xampp/htdocs/staging/'

all_passed = True

print("--- STARTING PERSISTENCE CHECK ---")

for file_rel, keywords in files.items():
    path = os.path.join(base_path, file_rel)
    # print(f"Checking {file_rel}...")
    try:
        with open(path, 'r', encoding='utf-8') as f:
            content = f.read()
            for kw in keywords:
                if kw in content:
                    print(f"[PASS] {file_rel}: Found '{kw}'")
                else:
                    print(f"[FAIL] {file_rel}: MISSING '{kw}'")
                    all_passed = False
    except Exception as e:
        print(f"[ERROR] Could not read {file_rel}: {e}")
        all_passed = False

if all_passed:
    print("\n--- ALL CHECKS PASSED: FILES ARE PERSISTED ---")
else:
    print("\n--- SOME CHECKS FAILED ---")
