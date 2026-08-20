(() => {
    'use strict';

    const wrap = document.getElementById('signaturePadWrap');
    const canvas = document.getElementById('pad');
    const clearBtn = document.getElementById('clear');

    if (!wrap || !canvas) return;

    wrap.classList.add('is-empty');

    const start = () => {
        wrap.classList.remove('is-empty');
        wrap.classList.add('is-signing');
    };

    const reset = () => {
        wrap.classList.remove('is-signing');
        wrap.classList.add('is-empty');
    };

    canvas.addEventListener('pointerdown', start, {passive:true});
    canvas.addEventListener('touchstart', start, {passive:true});
    canvas.addEventListener('mousedown', start, {passive:true});

    if (clearBtn) {
        clearBtn.addEventListener('click', () => setTimeout(reset, 0));
    }
})();
