        </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            Powered by <a href="https://netking.id" target="_blank" rel="noopener noreferrer">Netking</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <div class="modal fade" id="commandPaletteModal" tabindex="-1" aria-labelledby="commandPaletteTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="commandPaletteTitle">Command Palette</h5>
                        <div class="text-muted small">Cari halaman atau perangkat dengan cepat.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="text" class="form-control mb-3" id="command-palette-input" placeholder="Ketik Dashboard, Alarm, SN, customer, PPPoE, atau OLT...">
                    <div id="command-palette-results" class="list-group small">
                        <div class="list-group-item text-muted">Ketik minimal 2 karakter untuk cari perangkat.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="/assets/js/main.js?v=<?php echo time(); ?>"></script>
</body>
</html>
