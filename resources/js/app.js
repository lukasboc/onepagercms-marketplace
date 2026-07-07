

import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Assembles obfuscated email addresses (see x-obfuscated-email component)
// into real mailto links so the raw address never appears in the HTML source.
Alpine.data('obfuscatedEmail', () => ({
    init() {
        const address = atob(this.$el.dataset.user) + '@' + atob(this.$el.dataset.domain);
        const link = document.createElement('a');
        link.href = 'mailto:' + address;
        link.textContent = address;
        link.className = this.$el.className;
        this.$el.replaceChildren(link);
    },
}));

Alpine.start();
