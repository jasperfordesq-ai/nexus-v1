    </main>

    <script>
        // CSRF Token for forms
        function getCsrfToken() {
            return document.querySelector('input[name="csrf_token"]')?.value || '';
        }
    </script>
</body>
</html>
