        </main>
        
        <!-- App Footer -->
        <footer style="padding: 20px 28px; border-top: 1px solid var(--border-color); background: #FFFFFF; font-size: 0.82rem; color: var(--text-muted); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div>
                <strong><?= APP_NAME ?></strong> &copy; <?= date('Y') ?> &bull; Early Childhood Learning & Safety Platform
            </div>
            <div>
                Developed by: <strong><?= implode(', ', TEAM_MEMBERS) ?></strong>
            </div>
        </footer>
    </div>
</div>

<script src="<?= url('assets/js/main.js') ?>"></script>
<script src="<?= url('assets/js/calendar.js') ?>"></script>
</body>
</html>
