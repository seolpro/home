(() => {
    'use strict';

    const IMPORTANT = new Set([2, 4, 8, 10, 12, 13]);

    function getContractRoot() {
        return document.getElementById('contractDocument');
    }

    /**
     * 계약서 전체가 아니라 "제1조~제16조" 본문이 들어있는
     * 가장 작은 요소만 찾는다.
     * 이렇게 해야 상단 목적물/계약기간/보증금/월차임 요약표가 보존된다.
     */
    function findTermsRoot(contractRoot) {
        if (!contractRoot) return null;

        const all = [contractRoot, ...contractRoot.querySelectorAll('*')];

        const candidates = all.filter(el => {
            const text = (el.innerText || el.textContent || '').trim();

            return (
                /제\s*1\s*조/.test(text) &&
                /제\s*2\s*조/.test(text) &&
                /제\s*13\s*조/.test(text)
            );
        });

        if (!candidates.length) return null;

        candidates.sort((a, b) => {
            const aLen = (a.innerText || a.textContent || '').length;
            const bLen = (b.innerText || b.textContent || '').length;
            return aLen - bLen;
        });

        return candidates[0];
    }

    function existingClauseBlocks(termsRoot) {
        let clauses = [...termsRoot.querySelectorAll('[data-clause-no]')];

        if (clauses.length >= 2) {
            return clauses;
        }

        const candidates = [...termsRoot.querySelectorAll(
            'p, div, section, article, li'
        )].filter(el => {
            const text = (el.innerText || el.textContent || '').trim();

            if (!/^제\s*\d+\s*조(?:\s|\(|$)/.test(text)) {
                return false;
            }

            // 너무 큰 부모 요소는 제외
            const ownText = text.length;
            const childText = [...el.children]
                .reduce((sum, child) => {
                    return sum + ((child.innerText || child.textContent || '').length);
                }, 0);

            return el.children.length <= 12 || ownText - childText > 0;
        });

        candidates.forEach(el => {
            const text = (el.innerText || el.textContent || '').trim();
            const m = text.match(/^제\s*(\d+)\s*조/);

            if (m) {
                el.dataset.clauseNo = m[1];
            }
        });

        clauses = candidates.filter(el => el.dataset.clauseNo);

        return clauses;
    }

    /**
     * 조항 전체가 한 개 div 안에 nl2br 형태로 들어 있는 경우
     * "조항 본문 영역"만 재구성한다.
     * termsRoot 바깥의 요약표/계약 주요사항은 절대 건드리지 않는다.
     */
    function splitSingleTermsBlock(termsRoot) {
        const originalHtml = termsRoot.innerHTML;

        const normalized = originalHtml
            .replace(/<br\s*\/?>/gi, '\n')
            .replace(/<\/p>/gi, '</p>\n')
            .replace(/<\/div>/gi, '</div>\n');

        const plain = normalized
            .replace(/<[^>]+>/g, '')
            .replace(/&nbsp;/gi, ' ');

        const clauseMatches = [...plain.matchAll(/제\s*(\d+)\s*조(?:\s|\()/g)];

        if (clauseMatches.length < 2) {
            return [];
        }

        // HTML 자체에서 제N조 위치를 기준으로 나눈다.
        const htmlParts = originalHtml.split(
            /(?=제\s*\d+\s*조(?:\s|\())/g
        );

        const clauseParts = htmlParts.filter(part => {
            const text = part.replace(/<[^>]+>/g, '').trim();
            return /^제\s*\d+\s*조/.test(text);
        });

        if (clauseParts.length < 2) {
            return [];
        }

        const wrapper = document.createElement('div');
        wrapper.className = 'contract-terms-highlight-wrapper';

        clauseParts.forEach(part => {
            const plainText = part.replace(/<[^>]+>/g, '').trim();
            const m = plainText.match(/^제\s*(\d+)\s*조/);

            if (!m) return;

            const sec = document.createElement('section');
            sec.className = 'contract-clause-section';
            sec.dataset.clauseNo = m[1];
            sec.innerHTML = part;

            wrapper.appendChild(sec);
        });

        if (!wrapper.children.length) {
            return [];
        }

        // termsRoot만 교체. 상단 요약표 등은 그대로 유지됨.
        termsRoot.innerHTML = '';
        termsRoot.appendChild(wrapper);

        return [...wrapper.querySelectorAll('[data-clause-no]')];
    }

    function getClauses(termsRoot) {
        if (!termsRoot) return [];

        const existing = existingClauseBlocks(termsRoot);

        if (existing.length >= 2) {
            return existing;
        }

        return splitSingleTermsBlock(termsRoot);
    }

    function decorateClauses(clauses) {
        clauses.forEach(el => {
            el.classList.add('contract-clause-section');

            const no = Number(el.dataset.clauseNo || 0);

            if (!IMPORTANT.has(no)) {
                return;
            }

            el.classList.add('is-important');

            if (el.querySelector('.contract-important-badge')) {
                return;
            }

            const badge = document.createElement('span');
            badge.className = 'contract-important-badge';
            badge.textContent = '중요';

            const heading = el.querySelector(
                'h1, h2, h3, h4, strong, b'
            );

            if (heading) {
                heading.appendChild(badge);
                return;
            }

            const firstChild = el.firstChild;

            if (firstChild) {
                firstChild.after(badge);
            } else {
                el.prepend(badge);
            }
        });
    }

    function makeProgress(contractRoot, clauses) {
        if (
            !contractRoot ||
            contractRoot.parentNode.querySelector('.contract-reading-progress')
        ) {
            return null;
        }

        const progress = document.createElement('div');
        progress.className = 'contract-reading-progress';

        progress.innerHTML = `
            <span class="contract-reading-progress__text">
                계약서 읽는 중
            </span>

            <div class="contract-reading-progress__bar" aria-hidden="true">
                <div class="contract-reading-progress__fill"></div>
            </div>

            <span class="contract-reading-progress__count">
                1/${clauses.length}
            </span>
        `;

        contractRoot.parentNode.insertBefore(
            progress,
            contractRoot
        );

        return {
            text: progress.querySelector(
                '.contract-reading-progress__text'
            ),
            fill: progress.querySelector(
                '.contract-reading-progress__fill'
            ),
            count: progress.querySelector(
                '.contract-reading-progress__count'
            )
        };
    }

    function enableReadingHighlight(clauses, ui) {
        if (!clauses.length || !ui) return;

        let scheduled = false;
        let currentIndex = -1;

        function update() {
            scheduled = false;

            const targetY = window.innerHeight * 0.44;

            let bestIndex = 0;
            let bestDistance = Infinity;

            clauses.forEach((el, index) => {
                const rect = el.getBoundingClientRect();

                const nearestY = Math.max(
                    rect.top,
                    Math.min(targetY, rect.bottom)
                );

                const distance = Math.abs(
                    nearestY - targetY
                );

                if (distance < bestDistance) {
                    bestDistance = distance;
                    bestIndex = index;
                }
            });

            if (currentIndex === bestIndex) return;

            currentIndex = bestIndex;

            clauses.forEach((el, index) => {
                el.classList.toggle(
                    'is-reading',
                    index === bestIndex
                );
            });

            const active = clauses[bestIndex];
            const no = active.dataset.clauseNo || String(bestIndex + 1);

            ui.text.textContent =
                `현재 제${no}조 확인 중`;

            ui.count.textContent =
                `${bestIndex + 1}/${clauses.length}`;

            ui.fill.style.width =
                `${((bestIndex + 1) / clauses.length) * 100}%`;
        }

        function scheduleUpdate() {
            if (scheduled) return;

            scheduled = true;
            requestAnimationFrame(update);
        }

        window.addEventListener(
            'scroll',
            scheduleUpdate,
            { passive: true }
        );

        window.addEventListener(
            'resize',
            scheduleUpdate
        );

        scheduleUpdate();
    }

    function init() {
        const contractRoot = getContractRoot();

        if (!contractRoot) {
            console.warn(
                '[ContractHighlight] #contractDocument를 찾지 못했습니다.'
            );
            return;
        }

        const termsRoot = findTermsRoot(contractRoot);

        if (!termsRoot) {
            console.warn(
                '[ContractHighlight] 계약 조항 본문 영역을 찾지 못했습니다.'
            );
            return;
        }

        const clauses = getClauses(termsRoot);

        if (clauses.length < 2) {
            console.warn(
                '[ContractHighlight] 조항 분리에 실패했습니다.'
            );
            return;
        }

        decorateClauses(clauses);

        const ui = makeProgress(
            contractRoot,
            clauses
        );

        enableReadingHighlight(
            clauses,
            ui
        );

        console.log(
            `[ContractHighlight] ${clauses.length}개 조항 활성화 / 상단 요약표 보존`
        );
    }

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            init
        );
    } else {
        init();
    }
})();
