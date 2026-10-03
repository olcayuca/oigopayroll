/*
 * Panel calculator (assistant shortcut): immediate-execution, Turkish number format, keyboard support.
 * Runs in the browser only.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('oigoCalculator', () => ({
        current: '0',
        stored: null,
        operator: null,
        fresh: false,
        expression: '',
        history: [],
        copied: false,
        buttons: [
            { label: 'C', value: 'clear', kind: 'fn' }, { label: '⌫', value: 'back', kind: 'fn' }, { label: '%', value: '%', kind: 'fn' }, { label: '÷', value: '/', kind: 'op' },
            { label: '7', value: '7' }, { label: '8', value: '8' }, { label: '9', value: '9' }, { label: '×', value: '*', kind: 'op' },
            { label: '4', value: '4' }, { label: '5', value: '5' }, { label: '6', value: '6' }, { label: '−', value: '-', kind: 'op' },
            { label: '1', value: '1' }, { label: '2', value: '2' }, { label: '3', value: '3' }, { label: '+', value: '+', kind: 'op' },
            { label: '±', value: 'neg', kind: 'fn' }, { label: '0', value: '0' }, { label: ',', value: '.' }, { label: '=', value: '=', kind: 'eq' },
        ],
        symbols: { '/': '÷', '*': '×', '-': '−', '+': '+' },

        get display() {
            if (this.current === 'Hata') return this.current;
            const [whole, fraction] = this.current.split('.');
            const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            return fraction === undefined ? grouped : grouped + ',' + fraction;
        },

        format(number) {
            return Number(number).toLocaleString('tr-TR', { maximumFractionDigits: 10 });
        },

        compute(a, b, operator) {
            const result = { '+': a + b, '-': a - b, '*': a * b, '/': b === 0 ? NaN : a / b }[operator];
            return Number.isFinite(result) ? parseFloat(result.toPrecision(15)) : NaN;
        },

        setResult(number) {
            this.current = Number.isNaN(number) ? 'Hata' : String(number);
            this.fresh = true;
        },

        press(value) {
            if (this.current === 'Hata' && value !== 'clear') this.press('clear');

            if (/^\d$/.test(value)) {
                if (this.fresh || this.current === '0') { this.current = value; this.fresh = false; }
                else if (this.current.replace(/[-.]/g, '').length < 15) this.current += value;
            } else if (value === '.') {
                if (this.fresh) { this.current = '0.'; this.fresh = false; }
                else if (! this.current.includes('.')) this.current += '.';
            } else if (value === 'clear') {
                Object.assign(this, { current: '0', stored: null, operator: null, fresh: false, expression: '' });
            } else if (value === 'back') {
                if (! this.fresh) this.current = this.current.length > 1 && this.current !== '-0' ? this.current.slice(0, -1).replace(/^-$/, '0') : '0';
            } else if (value === 'neg') {
                this.current = this.current.startsWith('-') ? this.current.slice(1) : (this.current === '0' ? '0' : '-' + this.current);
            } else if (value === '%') {
                const number = parseFloat(this.current);
                // 1000 + 18 % → 1000 + 180 ; otherwise a plain percentage.
                this.setResult(this.stored !== null && ['+', '-'].includes(this.operator) ? this.stored * number / 100 : number / 100);
            } else if (['+', '-', '*', '/'].includes(value)) {
                if (this.operator !== null && ! this.fresh) {
                    this.setResult(this.compute(this.stored, parseFloat(this.current), this.operator));
                }
                this.stored = parseFloat(this.current);
                this.operator = value;
                this.fresh = true;
                this.expression = this.format(this.stored) + ' ' + this.symbols[value];
            } else if (value === '=' && this.operator !== null) {
                const b = parseFloat(this.current);
                const expression = this.format(this.stored) + ' ' + this.symbols[this.operator] + ' ' + this.format(b);
                this.setResult(this.compute(this.stored, b, this.operator));
                this.history = [{ expression: expression + ' =', result: this.display }, ...this.history].slice(0, 4);
                Object.assign(this, { stored: null, operator: null, expression: expression + ' =' });
            }
        },

        key(event) {
            if (! this.$root.offsetParent || event.target.closest('input, textarea')) return;
            const map = { Enter: '=', '=': '=', Backspace: 'back', Escape: null, Delete: 'clear', ',': '.', '.': '.', '%': '%', '+': '+', '-': '-', '*': '*', '/': '/', x: '*' };
            const value = /^\d$/.test(event.key) ? event.key : map[event.key];
            if (value) { event.preventDefault(); this.press(value); }
        },

        copy() {
            navigator.clipboard?.writeText(this.display).then(() => { this.copied = true; setTimeout(() => this.copied = false, 1500); });
        },
    }));
});
