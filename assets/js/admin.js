/* PGCO Sale - admin panel (plain JS + wp.media) */
(function () {
    'use strict';

    var $ = function (s, r) { return (r || document).querySelector(s); };
    var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
    var cfg = window.PGS_ADMIN || {};
    var seq = Date.now(); // unique index for new rows
    var current = { table: 0, name: '', rows: [] };

    /* ── image selection ── */
    document.addEventListener('click', function (e) {
        var pick = e.target.closest('[data-pgs-pick]');
        var clear = e.target.closest('[data-pgs-clear]');
        if (pick) {
            var box = pick.closest('[data-pgs-media]');
            var frame = wp.media({ title: 'انتخاب تصویر', multiple: false, library: { type: 'image' } });
            frame.on('select', function () {
                var a = frame.state().get('selection').first().toJSON();
                setMedia(box, a.id, (a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url));
            });
            frame.open();
        }
        if (clear) setMedia(clear.closest('[data-pgs-media]'), 0, '');
    });

    function setMedia(box, id, url) {
        $('input[type=hidden]', box).value = id;
        var thumb = $('.pgs-media__thumb', box);
        thumb.innerHTML = url ? '<img src="' + url + '" alt="">' : '';
        var c = $('[data-pgs-clear]', box);
        if (c) c.hidden = !id;
    }

    /* ── loading the table rows ── */
    var rowsWrap = $('#pgs-rows-wrap');
    var rowsUl = $('#pgs-rows');
    var items = $('#pgs-items');

    function existing() {
        var map = {};
        $$('[data-pgs-item]', items).forEach(function (li) {
            var t = $('input[name$="[table_id]"]', li).value;
            var k = $('input[name$="[row_key]"]', li).value;
            map[t + ':' + k] = true;
        });
        return map;
    }

    function renderRows() {
        var have = existing();
        rowsUl.innerHTML = '';
        current.rows.forEach(function (r, i) {
            var li = document.createElement('li');
            var added = have[current.table + ':' + r.key];
            li.className = (r.disabled ? 'is-disabled ' : '') + (added ? 'is-added' : '');
            li.dataset.index = i;
            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.disabled = !!r.disabled || !!added;
            var label = document.createElement('label');
            label.style.flex = '1';
            label.appendChild(cb);
            label.appendChild(document.createTextNode(' ' + r.label + (added ? ' (افزوده شده)' : '')));
            var price = document.createElement('span');
            price.className = 'price';
            price.textContent = r.disabled ? 'بدون قیمت' : r.price;
            li.appendChild(label);
            li.appendChild(price);
            rowsUl.appendChild(li);
        });
        applyFilter();
    }

    function visibleBoxes() {
        return $$('li', rowsUl).filter(function (li) { return li.style.display !== 'none'; })
            .map(function (li) { return $('input[type=checkbox]', li); })
            .filter(function (cb) { return cb && !cb.disabled; });
    }

    function applyFilter() {
        var q = ($('#pgs-filter').value || '').trim().toLowerCase();
        $$('li', rowsUl).forEach(function (li) { li.style.display = !q || li.textContent.toLowerCase().indexOf(q) > -1 ? '' : 'none'; });
        $('#pgs-count').textContent = visibleBoxes().length + ' ردیف قابل انتخاب';
    }

    $('#pgs-load').addEventListener('click', function () {
        var id = $('#pgs-table').value;
        if (!id) return;
        var btn = this;
        btn.disabled = true;
        fetch(cfg.ajax + '?action=pgco_sale_rows&nonce=' + encodeURIComponent(cfg.nonce) + '&table=' + encodeURIComponent(id), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success) throw new Error('خطا');
                current = { table: parseInt(id, 10), name: res.data.name, rows: res.data.rows };
                rowsWrap.hidden = false;
                $('#pgs-all').checked = false;
                renderRows();
            })
            .catch(function () { alert('بارگذاری ردیف‌ها ناموفق بود.'); })
            .then(function () { btn.disabled = false; });
    });
    $('#pgs-filter').addEventListener('input', applyFilter);
    $('#pgs-all').addEventListener('change', function () {
        var on = this.checked;
        visibleBoxes().forEach(function (cb) { cb.checked = on; });
    });

    /* ── adding items ── */
    function nextIndex() { return 'n' + (seq++); }

    function cloneTemplate(id) {
        var idx = nextIndex();
        var html = $(id).innerHTML.replace(/__i__/g, idx);
        var tmp = document.createElement('div');
        tmp.innerHTML = html.trim();
        return tmp.firstElementChild;
    }

    function syncMode(li) {
        var mode = ($('[data-pgs-mode]:checked', li) || {}).value || 'percent';
        $$('[data-pgs-when]', li).forEach(function (el) { el.hidden = el.dataset.pgsWhen !== mode; });
    }

    $('#pgs-add').addEventListener('click', function () {
        var percent = $('#pgs-def-percent').value || '0';
        var image = $('#pgs-def-image').value || '0';
        var thumb = $('#pgs-def-image-wrap .pgs-media__thumb img');
        var added = 0;
        $$('li', rowsUl).forEach(function (li) {
            var cb = $('input[type=checkbox]', li);
            if (!cb || !cb.checked || cb.disabled) return;
            var r = current.rows[parseInt(li.dataset.index, 10)];
            var item = cloneTemplate('#pgs-item-tpl');
            var set = function (suffix, v) { var f = $('[name$="' + suffix + '"]', item); if (f) f.value = v; };
            set('[table_id]', current.table);
            set('[row_key]', r.key);
            set('[source]', current.name + ' — ' + r.label);
            set('[title]', r.title);
            set('[unit]', r.unit);
            set('[percent]', percent);
            if (image !== '0') {
                var box = $('[data-pgs-media]', item);
                setMedia(box, image, thumb ? thumb.src : '');
            }
            $('[data-pgs-name]', item).textContent = r.title;
            $('[data-pgs-live]', item).textContent = 'قیمت فعلی جدول: ' + r.price + ' (پس از ذخیره، قیمت جدید محاسبه می‌شود)';
            syncMode(item);
            items.appendChild(item);
            added++;
        });
        $('#pgs-empty').hidden = !!$$('[data-pgs-item]', items).length;
        if (added) renderRows();
    });

    /* ── per-item controls ── */
    items.addEventListener('click', function (e) {
        var li = e.target.closest('[data-pgs-item]');
        if (!li) return;
        if (e.target.closest('[data-pgs-remove]')) {
            if (confirm('این محصول از فهرست حذف شود؟')) {
                li.remove();
                $('#pgs-empty').hidden = !!$$('[data-pgs-item]', items).length;
                if (!rowsWrap.hidden) renderRows();
            }
        }
        if (e.target.closest('[data-pgs-up]') && li.previousElementSibling) items.insertBefore(li, li.previousElementSibling);
        if (e.target.closest('[data-pgs-down]') && li.nextElementSibling) items.insertBefore(li.nextElementSibling, li);
    });
    items.addEventListener('change', function (e) {
        if (e.target.matches('[data-pgs-mode]')) syncMode(e.target.closest('[data-pgs-item]'));
    });
    items.addEventListener('input', function (e) {
        if (e.target.matches('[data-pgs-title]')) $('[data-pgs-name]', e.target.closest('[data-pgs-item]')).textContent = e.target.value;
    });
    $$('[data-pgs-item]', items).forEach(syncMode);

    /* ── drag and drop (from the handle only) ── */
    var dragged = null;
    items.addEventListener('mousedown', function (e) {
        var li = e.target.closest('[data-pgs-item]');
        if (li) li.draggable = !!e.target.closest('[data-pgs-handle]');
    });
    items.addEventListener('dragstart', function (e) {
        dragged = e.target.closest('[data-pgs-item]');
        if (!dragged) return;
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', '');
        dragged.classList.add('is-dragging');
    });
    items.addEventListener('dragover', function (e) {
        var over = e.target.closest('[data-pgs-item]');
        if (!dragged || !over || over === dragged) return;
        e.preventDefault();
        $$('.is-over', items).forEach(function (n) { n.classList.remove('is-over'); });
        over.classList.add('is-over');
    });
    items.addEventListener('drop', function (e) {
        var over = e.target.closest('[data-pgs-item]');
        if (!dragged || !over || over === dragged) return;
        e.preventDefault();
        items.insertBefore(dragged, over);
    });
    items.addEventListener('dragend', function () {
        if (dragged) { dragged.classList.remove('is-dragging'); dragged.draggable = false; }
        $$('.is-over', items).forEach(function (n) { n.classList.remove('is-over'); });
        dragged = null;
    });

    /* ── decorative images ── */
    var decor = $('#pgs-decor');
    $('#pgs-decor-add').addEventListener('click', function () { decor.appendChild(cloneTemplate('#pgs-decor-tpl')); });
    decor.addEventListener('click', function (e) {
        if (e.target.closest('[data-pgs-decor-remove]')) e.target.closest('[data-pgs-decor-row]').remove();
    });
})();
