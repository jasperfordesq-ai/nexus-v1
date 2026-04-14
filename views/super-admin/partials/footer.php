<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.
?>
    </main>

    <script>
        // CSRF Token for forms
        function getCsrfToken() {
            return document.querySelector('input[name="csrf_token"]')?.value || '';
        }
    </script>
</body>
</html>
