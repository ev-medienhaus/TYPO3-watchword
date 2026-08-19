(function () {
    'use strict';

    const formatDate = (dateTime) => {
        const [year, month, day] = dateTime.split('-');
        return `${day}.${month}.${year}`;
    };

    const formatVisibleDate = (day) => {
        if (!day.weekday) {
            return day.date;
        }

        return day.sundayName
            ? `${day.date} (${day.weekday} \u2013 ${day.sundayName})`
            : `${day.date} (${day.weekday})`;
    };

    const buildAjaxUrl = (endpoint, date, direction) => {
        const isTypeNum = /^\d+$/.test(String(endpoint).trim());
        const url = isTypeNum
            ? new URL(globalThis.location.pathname, globalThis.location.origin)
            : new URL(endpoint, globalThis.location.origin);

        if (isTypeNum) {
            url.searchParams.set('type', String(endpoint).trim());
        }

        url.searchParams.set('date', date);
        url.searchParams.set('direction', direction);

        return url;
    };

    const getDay = async (watchwords, direction) => {
        const directionName = direction < 0 ? 'previous' : 'next';
        const endpoint = watchwords.dataset.watchwordsEndpoint;

        if (!endpoint) {
            throw new Error('The watchwords endpoint is not configured.');
        }

        const currentDateTime = watchwords.querySelector('[data-watchwords-date]').dateTime;
        const date = formatDate(currentDateTime);
        const response = await fetch(buildAjaxUrl(endpoint, date, directionName), {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error(`The watchwords request failed with status ${response.status}.`);
        }

        const payload = await response.json();
        const day = payload.data ?? payload;
        const normalizedDay = {
            ...day,
            date: day.date ?? date,
            dateTime: day.dateTime ?? currentDateTime,
            weekday: day.weekday ?? '',
            sundayName: day.sundayName ?? '',
        };

        if (
            !Array.isArray(normalizedDay.content) ||
            normalizedDay.content.length < 2 ||
            normalizedDay.content.some(
                (item) => typeof item.quote !== 'string' || typeof item.reference !== 'string',
            )
        ) {
            throw new Error('The watchwords response has an invalid format.');
        }

        return normalizedDay;
    };

    const copyText = async (text) => {
        if (navigator.clipboard?.writeText && globalThis.isSecureContext) {
            await navigator.clipboard.writeText(text);
            return;
        }

        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.append(textarea);
        textarea.select();

        const wasCopied = document.execCommand('copy');
        textarea.remove();

        if (!wasCopied) {
            throw new Error('Copying the watchword text failed.');
        }
    };

    const renderDay = (watchwords, day) => {
        const date = watchwords.querySelector('[data-watchwords-date]');
        const contentItems = watchwords.querySelectorAll('[data-watchwords-content]');

        date.textContent = formatVisibleDate(day);
        date.dateTime = day.dateTime;

        for (const [index, contentItem] of contentItems.entries()) {
            const data = day.content[index];
            contentItem.querySelector('[data-watchwords-quote]').textContent = data.quote;
            contentItem.querySelector('[data-watchwords-reference]').textContent = data.reference;
        }
    };

    const initWatchwords = (watchwords) => {
        const previousButton = watchwords.querySelector('[data-watchwords-previous]');
        const nextButton = watchwords.querySelector('[data-watchwords-next]');
        const navigationButtons = [previousButton, nextButton];
        const status = watchwords.querySelector('[data-watchwords-status]');
        const feedbackTimers = new Map();

        const changeDay = async (direction) => {
            if (watchwords.classList.contains('is-loading')) {
                return;
            }

            watchwords.classList.add('is-loading');
            watchwords.setAttribute('aria-busy', 'true');

            for (const button of navigationButtons) {
                button.disabled = true;
            }

            try {
                const day = await getDay(watchwords, direction);
                renderDay(watchwords, day);
                status.textContent = '';
                watchwords.dispatchEvent(new CustomEvent('watchwords:change', { detail: day }));
            } catch (error) {
                status.textContent = 'Losung konnte nicht geladen werden';
                watchwords.dispatchEvent(new CustomEvent('watchwords:error', { detail: error }));
            } finally {
                for (const button of navigationButtons) {
                    button.disabled = false;
                }

                watchwords.classList.remove('is-loading');
                watchwords.removeAttribute('aria-busy');
            }
        };

        previousButton.addEventListener('click', () => changeDay(-1));
        nextButton.addEventListener('click', () => changeDay(1));

        for (const shareButton of watchwords.querySelectorAll('[data-watchwords-share]')) {
            shareButton.addEventListener('click', async () => {
                const label = shareButton.querySelector('[data-watchwords-share-label]');
                const contentItem = shareButton.closest('[data-watchwords-content]');
                const quote = contentItem.querySelector('[data-watchwords-quote]').textContent.trim();
                const reference = contentItem.querySelector('[data-watchwords-reference]').textContent.trim();
                const shareText = reference ? `${quote} , ${reference}` : quote;

                if (feedbackTimers.has(shareButton)) {
                    clearTimeout(feedbackTimers.get(shareButton));
                }

                try {
                    await copyText(shareText);
                    label.textContent = 'Text kopiert';
                    status.textContent = 'Text kopiert';
                } catch {
                    label.textContent = 'Kopieren fehlgeschlagen';
                    status.textContent = 'Text konnte nicht kopiert werden';
                }

                feedbackTimers.set(
                    shareButton,
                    setTimeout(() => {
                        label.textContent = 'Teilen';
                        status.textContent = '';
                        feedbackTimers.delete(shareButton);
                    }, 2500),
                );
            });
        }
    };

    const init = () => {
        for (const watchwords of document.querySelectorAll('[data-watchwords]')) {
            initWatchwords(watchwords);
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
