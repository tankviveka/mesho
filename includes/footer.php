<?php
/** Common page footer: closing tags, scripts. */

$hasCharts = $hasCharts ?? false;
?>
        </main>

        <footer class="app-footer">
            <span>&copy; <?= e(date('Y')) ?> <?= e(APP_NAME) ?> &mdash; <?= e(APP_TAGLINE) ?></span>
            <span class="footer-note">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                DEMO MODE. All orders, products and payments are generated sample records.
                This project is not affiliated with, endorsed by, or connected to Meesho.
            </span>
        </footer>
    </div>
</div>

<script src="assets/vendor/bootstrap.bundle.min.js"></script>
<?php if ($hasCharts): ?>
<script src="assets/vendor/chart.umd.min.js"></script>
<?php endif; ?>
<script src="assets/js/app.js"></script>
</body>
</html>
