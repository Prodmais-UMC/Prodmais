/**
 * PRODMAIS UMC - Autocomplete de pesquisadores
 * Anexa sugestões a qualquer input com data-autocomplete="pesquisador".
 * Navegação: setas para percorrer, Enter/Tab ou clique para selecionar,
 * Escape para fechar. Ao selecionar, vai direto para as produções do
 * pesquisador escolhido.
 */
(function () {
    function debounce(fn, wait) {
        var t;
        return function () {
            var args = arguments;
            var ctx = this;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(ctx, args); }, wait);
        };
    }

    function attachAutocomplete(input) {
        var wrap = input.closest('[data-autocomplete-wrap]') || input.parentNode;
        wrap.style.position = wrap.style.position || 'relative';

        var list = document.createElement('ul');
        list.className = 'ac-suggest-list';
        wrap.appendChild(list);

        var items = [];
        var activeIndex = -1;

        function hide() {
            list.classList.remove('is-open');
            list.innerHTML = '';
            items = [];
            activeIndex = -1;
        }

        function updateActive() {
            Array.prototype.forEach.call(list.children, function (li, i) {
                li.classList.toggle('is-active', i === activeIndex);
            });
        }

        function render(sugestoes) {
            items = sugestoes;
            activeIndex = -1;
            if (!sugestoes.length) {
                hide();
                return;
            }
            list.innerHTML = sugestoes.map(function (s, i) {
                var instituicao = s.instituicao ? '<span class="ac-suggest-sub">' + s.instituicao + '</span>' : '';
                return '<li class="ac-suggest-item" data-index="' + i + '">' +
                    '<i class="fas fa-user" aria-hidden="true"></i>' +
                    '<span class="ac-suggest-main">' + s.nome + instituicao + '</span>' +
                    '</li>';
            }).join('');
            list.classList.add('is-open');
        }

        function selectItem(i) {
            if (!items[i]) {
                return;
            }
            var destino = input.getAttribute('data-autocomplete-target') || '/result.php?pesquisador=';
            window.location.href = destino + encodeURIComponent(items[i].nome);
        }

        var buscarSugestoes = debounce(function () {
            var termo = input.value.trim();
            if (termo.length < 2) {
                hide();
                return;
            }
            fetch('/api/autocomplete.php?q=' + encodeURIComponent(termo))
                .then(function (r) { return r.ok ? r.json() : { sugestoes: [] }; })
                .then(function (data) { render(data.sugestoes || []); })
                .catch(function () { hide(); });
        }, 250);

        input.setAttribute('autocomplete', 'off');
        input.addEventListener('input', buscarSugestoes);

        input.addEventListener('keydown', function (e) {
            if (!list.classList.contains('is-open')) {
                return;
            }
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIndex = Math.min(activeIndex + 1, items.length - 1);
                updateActive();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIndex = Math.max(activeIndex - 1, 0);
                updateActive();
            } else if (e.key === 'Enter' || e.key === 'Tab') {
                if (activeIndex >= 0) {
                    e.preventDefault();
                    selectItem(activeIndex);
                }
            } else if (e.key === 'Escape') {
                hide();
            }
        });

        list.addEventListener('mousedown', function (e) {
            var li = e.target.closest('.ac-suggest-item');
            if (li) {
                selectItem(Number(li.getAttribute('data-index')));
            }
        });

        document.addEventListener('click', function (e) {
            if (!wrap.contains(e.target)) {
                hide();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-autocomplete="pesquisador"]').forEach(attachAutocomplete);
    });
})();
