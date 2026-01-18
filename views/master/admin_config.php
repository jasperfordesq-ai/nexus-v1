// views/master/admin_config.php

$config = json_decode($tenant['configuration'] ?? '{"modules": {}}', true);
$modules = $config['modules'] ?? [];


return;
}

require_once __DIR__ . '/../../views/layouts/header.php';

$id = $_GET['id'] ?? 0;
$tenant = \Nexus\Models\Tenant::find($id);

if (!$tenant) {
echo "Tenant not found.";
exit;
}

$config = json_decode($tenant['configuration'] ?? '{"modules": {}}', true);
$modules = $config['modules'] ?? [];
?>

<div class="nexus-container">
    <div class="nexus-card">
        <h1>Mobile App Configuration: <?= htmlspecialchars($tenant['name']) ?></h1>
        <p>Enable or disable features for this tenant's mobile app users.</p>

        <form id="configForm">
            <input type="hidden" name="tenant_id" value="<?= $tenant['id'] ?>">

            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; margin-top: 20px;">
                <?php
                $availableModules = ['events', 'polls', 'goals', 'volunteering', 'resources'];
                foreach ($availableModules as $mod):
                    $checked = !empty($modules[$mod]) ? 'checked' : '';
                ?>
                    <label class="nexus-card" style="cursor: pointer; display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" name="modules[<?= $mod ?>]" <?= $checked ?>>
                        <span style="text-transform: capitalize; font-weight: bold;"><?= $mod ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <button type="button" onclick="saveConfig()" class="nexus-btn nexus-btn-primary" style="margin-top: 20px;">Save Configuration</button>
            <a href="/super-admin/tenant/edit?id=<?= $tenant['id'] ?>" class="nexus-btn nexus-btn-secondary">Back</a>
        </form>
    </div>
</div>

<script>
    async function saveConfig() {
        const form = document.getElementById('configForm');
        const formData = new FormData(form);
        const modules = {};

        // Collect checkboxes
        ['events', 'polls', 'goals', 'volunteering', 'resources'].forEach(mod => {
            modules[mod] = form.querySelector(`input[name="modules[${mod}]"]`).checked;
        });

        const payload = {
            tenant_id: formData.get('tenant_id'),
            config: {
                modules
            }
        };

        try {
            const res = await fetch(NEXUS_BASE + '/super-admin/tenant/update-config', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            });
            const json = await res.json();

            if (json.success) {
                alert('Configuration saved!');
            } else {
                alert('Error: ' + json.error);
            }
        } catch (e) {
            alert('Network Error');
        }
    }
</script>

<?php require_once __DIR__ . '/../../views/layouts/footer.php'; ?>