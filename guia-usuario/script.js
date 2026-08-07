(function () {
    'use strict';

    // ── Mantém --topbar-h em sincronia com a altura real do cabeçalho ──
    // (ele quebra em duas linhas no mobile, então um valor fixo no CSS
    // fica fora de sincronia com zoom, tamanho de fonte ou quebra de texto)
    var topbarEl = document.querySelector('.topbar');
    function sincronizarAlturaTopbar() {
        if (topbarEl) {
            document.documentElement.style.setProperty('--topbar-h', topbarEl.offsetHeight + 'px');
        }
    }
    sincronizarAlturaTopbar();
    window.addEventListener('resize', sincronizarAlturaTopbar);
    if (window.ResizeObserver && topbarEl) {
        new ResizeObserver(sincronizarAlturaTopbar).observe(topbarEl);
    }

    var sections = Array.prototype.slice.call(document.querySelectorAll('.doc-section'));
    var sidebarLinks = Array.prototype.slice.call(document.querySelectorAll('.sidebar-link'));
    var topbarTabs = Array.prototype.slice.call(document.querySelectorAll('.topbar-tab'));
    var tocNav = document.getElementById('tocNav');
    var sidebar = document.getElementById('sidebar');

    // ── Navegação por clique (sidebar + abas do topo) ──
    sidebarLinks.forEach(function (link) {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            var target = document.getElementById(link.dataset.target);
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
            sidebar.classList.remove('open');
        });
    });

    topbarTabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            var firstOfGroup = document.querySelector('.doc-section[data-group="' + tab.dataset.group + '"]');
            if (firstOfGroup) {
                firstOfGroup.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    // ── Menu mobile ──
    var mobileToggle = document.getElementById('mobileNavToggle');
    if (mobileToggle) {
        mobileToggle.addEventListener('click', function () {
            sidebar.classList.toggle('open');
        });
    }

    // ── Monta o "Nesta seção" (TOC direita) a partir dos h3 da seção ativa ──
    function montarToc(secao) {
        tocNav.innerHTML = '';
        if (!secao) return;

        var titulo = secao.querySelector('.doc-title');
        var subtitulos = secao.querySelectorAll('h3');

        if (titulo) {
            var linkTitulo = document.createElement('a');
            linkTitulo.href = '#' + secao.id;
            linkTitulo.textContent = titulo.textContent;
            linkTitulo.dataset.ref = secao.id;
            tocNav.appendChild(linkTitulo);
        }

        subtitulos.forEach(function (h3, i) {
            if (!h3.id) h3.id = secao.id + '-' + i;
            var link = document.createElement('a');
            link.href = '#' + h3.id;
            link.textContent = h3.textContent;
            link.dataset.ref = h3.id;
            tocNav.appendChild(link);
        });
    }

    // ── Observa qual seção está visível e sincroniza sidebar + abas + toc ──
    var secaoAtivaId = null;

    function ativarSecao(id) {
        if (id === secaoAtivaId) return;
        secaoAtivaId = id;

        var secao = document.getElementById(id);
        if (!secao) return;

        sidebarLinks.forEach(function (l) {
            l.classList.toggle('active', l.dataset.target === id);
        });
        topbarTabs.forEach(function (t) {
            t.classList.toggle('active', t.dataset.group === secao.dataset.group);
        });
        montarToc(secao);
    }

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                ativarSecao(entry.target.id);
            }
        });
    }, { rootMargin: '-15% 0px -70% 0px', threshold: 0 });

    sections.forEach(function (s) { observer.observe(s); });

    if (sections[0]) ativarSecao(sections[0].id);

    // ── Tema claro/escuro ──
    var themeToggle = document.getElementById('themeToggle');
    var root = document.documentElement;
    var temaSalvo = localStorage.getItem('prodmais-guia-tema');
    if (temaSalvo) root.setAttribute('data-theme', temaSalvo);

    themeToggle.addEventListener('click', function () {
        var atual = root.getAttribute('data-theme');
        var prefereEscuro = window.matchMedia('(prefers-color-scheme: dark)').matches;
        var novo;
        if (!atual) {
            novo = prefereEscuro ? 'light' : 'dark';
        } else {
            novo = atual === 'dark' ? 'light' : 'dark';
        }
        root.setAttribute('data-theme', novo);
        localStorage.setItem('prodmais-guia-tema', novo);
    });

    // ── Busca simples: filtra os itens da sidebar pelo texto de cada seção ──
    var searchInput = document.getElementById('searchInput');

    function textoDaSecao(id) {
        var el = document.getElementById(id);
        return el ? el.textContent.toLowerCase() : '';
    }

    searchInput.addEventListener('input', function () {
        var termo = searchInput.value.trim().toLowerCase();
        sidebarLinks.forEach(function (link) {
            if (!termo) {
                link.style.display = '';
                return;
            }
            var corresponde = link.textContent.toLowerCase().indexOf(termo) > -1
                || textoDaSecao(link.dataset.target).indexOf(termo) > -1;
            link.style.display = corresponde ? '' : 'none';
        });
    });

    searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            var primeiroVisivel = sidebarLinks.find(function (l) { return l.style.display !== 'none'; });
            if (primeiroVisivel) {
                primeiroVisivel.click();
                searchInput.blur();
            }
        }
    });

    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            searchInput.focus();
        }
    });
})();
