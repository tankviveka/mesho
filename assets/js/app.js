/* =============================================================================
   Meesho Seller Dashboard - DEMO
   Front-end behaviour: sidebar, filters, confirmations and Chart.js charts.
   ========================================================================== */

(function () {
  'use strict';

  /* ------------------------------ Sidebar ------------------------------- */
  var body = document.body;
  var openBtn = document.getElementById('sidebarOpen');
  var closeBtn = document.getElementById('sidebarClose');
  var overlay = document.getElementById('sidebarOverlay');

  function openSidebar() {
    body.classList.add('sidebar-open');
    if (overlay) overlay.hidden = false;
  }
  function closeSidebar() {
    body.classList.remove('sidebar-open');
    if (overlay) overlay.hidden = true;
  }

  if (openBtn) openBtn.addEventListener('click', openSidebar);
  if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
  if (overlay) overlay.addEventListener('click', closeSidebar);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeSidebar();
  });

  /* --------------------------- Auto-submit filters ---------------------- */
  // <select data-autosubmit> re-submits the GET form as soon as it changes.
  document.querySelectorAll('select[data-autosubmit]').forEach(function (el) {
    el.addEventListener('change', function () {
      var form = el.closest('form');
      if (form) {
        // Reset to page 1 whenever a filter changes.
        var pageInput = form.querySelector('input[name="page"]');
        if (pageInput) pageInput.value = '1';
        form.submit();
      }
    });
  });

  // Debounced auto-submit for the search box when typing stops.
  var searchTimer = null;
  document.querySelectorAll('input[data-autosubmit]').forEach(function (el) {
    el.addEventListener('input', function () {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(function () {
        var form = el.closest('form');
        if (form) {
          var pageInput = form.querySelector('input[name="page"]');
          if (pageInput) pageInput.value = '1';
          form.submit();
        }
      }, 650);
    });
  });

  // Clear-filters link resets the form to a bare URL.
  document.querySelectorAll('[data-reset-filters]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      e.preventDefault();
      var target = el.getAttribute('data-reset-filters');
      if (target) window.location.href = target;
    });
  });

  /* --------------------------- Confirm actions -------------------------- */
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      if (!window.confirm(el.getAttribute('data-confirm'))) {
        e.preventDefault();
      }
    });
  });

  /* ------------------------------ Tooltips ------------------------------ */
  if (window.bootstrap && bootstrap.Tooltip) {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
      new bootstrap.Tooltip(el);
    });
  }

  /* ------------------------------ Print --------------------------------- */
  document.querySelectorAll('[data-print]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      e.preventDefault();
      window.print();
    });
  });

  /* ------------------------------ Charts -------------------------------- */
  var MS_COLORS = {
    purple: '#7B2CBF',
    purpleLight: '#9D4EDD',
    pink: '#F43397',
    green: '#16A34A',
    amber: '#F59E0B',
    red: '#E11D48',
    cyan: '#0EA5E9',
    slate: '#94A3B8'
  };

  function rgba(hex, alpha) {
    var h = hex.replace('#', '');
    if (h.length === 3) h = h.split('').map(function (c) { return c + c; }).join('');
    var n = parseInt(h, 16);
    return 'rgba(' + ((n >> 16) & 255) + ',' + ((n >> 8) & 255) + ',' + (n & 255) + ',' + alpha + ')';
  }

  /* Number formatting -------------------------------------------------- */
  function fmtNumber(v) {
    var n = Number(v);
    if (isNaN(n)) return v;
    return n.toLocaleString('en-IN', { maximumFractionDigits: 0 });
  }

  // Compact rupee label used on chart axes: 1.2L, 34.5K, 3.05Cr
  function fmtInrShort(v) {
    var n = Number(v);
    if (isNaN(n)) return v;
    var neg = n < 0;
    var abs = Math.abs(n);
    var out;
    if (abs >= 10000000) out = (abs / 10000000).toFixed(2) + 'Cr';
    else if (abs >= 100000) out = (abs / 100000).toFixed(2) + 'L';
    else if (abs >= 1000) out = (abs / 1000).toFixed(1) + 'K';
    else out = abs.toLocaleString('en-IN', { maximumFractionDigits: 0 });
    return (neg ? '-' : '') + '₹' + out;
  }

  // Full rupee label used inside tooltips: ₹1,23,456
  function fmtInrFull(v) {
    var n = Number(v);
    if (isNaN(n)) return v;
    var neg = n < 0;
    var abs = Math.abs(n);
    return (neg ? '-' : '') + '₹' + abs.toLocaleString('en-IN', {
      minimumFractionDigits: abs % 1 !== 0 ? 2 : 0,
      maximumFractionDigits: 2
    });
  }

  function fmtByKind(v, kind) {
    if (kind === 'inr') return fmtInrFull(v);
    if (kind === 'inr-short') return fmtInrShort(v);
    return fmtNumber(v);
  }

  function readConfig(id) {
    var el = document.getElementById(id);
    if (!el) return null;
    try { return JSON.parse(el.textContent); } catch (err) { return null; }
  }

  function applySharedDefaults(config) {
    config.options = config.options || {};
    config.options.responsive = true;
    config.options.maintainAspectRatio = false;

    var opts = config.options;
    opts.plugins = opts.plugins || {};
    opts.plugins.legend = opts.plugins.legend || {};
    if (!opts.plugins.legend.labels) opts.plugins.legend.labels = {};
    opts.plugins.legend.labels.usePointStyle = true;
    opts.plugins.legend.labels.boxWidth = 8;
    opts.plugins.legend.labels.padding = 14;
    opts.plugins.legend.labels.color = '#5B4B6E';
    opts.plugins.legend.labels.font = { size: 12, weight: '600' };

    opts.plugins.tooltip = opts.plugins.tooltip || {};
    opts.plugins.tooltip.backgroundColor = 'rgba(60,9,108,.94)';
    opts.plugins.tooltip.padding = 11;
    opts.plugins.tooltip.cornerRadius = 9;
    opts.plugins.tooltip.titleFont = { size: 12.5, weight: '700' };
    opts.plugins.tooltip.bodyFont = { size: 12.5 };
    opts.plugins.tooltip.displayColors = false;
    opts.plugins.tooltip.callbacks = opts.plugins.tooltip.callbacks || {};
    opts.plugins.tooltip.callbacks.label = function (ctx) {
      var label = ctx.dataset && ctx.dataset.label ? ctx.dataset.label + ': ' : '';
      var kind = ctx.dataset ? ctx.dataset.msFormat : null;
      var raw = ctx.parsed;
      if (raw !== null && typeof raw === 'object') {
        raw = raw.y !== undefined ? raw.y : raw.x;
      }
      if (raw === undefined || raw === null) raw = ctx.raw;
      return label + fmtByKind(raw, kind);
    };

    opts.scales = opts.scales || {};
    Object.keys(opts.scales).forEach(function (key) {
      var axis = opts.scales[key];
      axis.grid = axis.grid || {};
      if (axis.grid.color === undefined) axis.grid.color = 'rgba(123,44,191,.09)';
      axis.border = axis.border || { display: false };
      axis.ticks = axis.ticks || {};
      axis.ticks.color = '#85779A';
      axis.ticks.font = axis.ticks.font || {};
      axis.ticks.font.size = 11.5;
      var kind = axis.ticks.msFormat || null;
      delete axis.ticks.msFormat;
      if (kind && typeof axis.ticks.callback !== 'function') {
        axis.ticks.callback = function (value) {
          return kind === 'inr' ? fmtInrShort(value) : fmtByKind(value, kind);
        };
      } else if (!axis.ticks.callback) {
        axis.ticks.callback = function (value) { return fmtNumber(value); };
      }
    });
    return config;
  }

  function initCharts() {
    if (typeof window.Chart === 'undefined') {
      // Chart.js not loaded on this page (or blocked) - tables still work.
      document.querySelectorAll('[data-chart-config]').forEach(function (canvas) {
        canvas.insertAdjacentHTML('afterend',
          '<div class="empty-state py-4"><small>Chart library unavailable - see the tables below for the same data.</small></div>');
      });
      return;
    }

    document.querySelectorAll('[data-chart-config]').forEach(function (canvas) {
      var config = readConfig(canvas.getAttribute('data-chart-config'));
      if (!config) return;
      config = applySharedDefaults(config);
      window.chartInstances = window.chartInstances || [];
      window.chartInstances.push(new Chart(canvas.getContext('2d'), config));
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCharts);
  } else {
    initCharts();
  }

  // Expose palette for any inline JSON configs built by PHP.
  window.MS_COLORS = MS_COLORS;
  window.MS_rgba = rgba;
})();
