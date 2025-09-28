(function () {
    const { BaseComponent, h, api } = window.DAB || {};
    if (!BaseComponent) { return; }

    class IntranetApp extends BaseComponent {
        constructor(props) {
            super(props);
            this.state = {
                loading: true,
                error: null,
                user: props.data.user || null,
                navigation: [],
                activeNode: null,
            };
        }

        mount(root) {
            super.mount(root);
            this.bootstrap();
        }

        setState(patch) {
            this.state = Object.assign({}, this.state, patch);
            this.update();
        }

        async bootstrap() {
            try {
                const data = await api('/api/intranet/bootstrap');
                if (data && data.error) {
                    throw new Error(data.error);
                }
                const navigation = Array.isArray(data.navigation) ? data.navigation : [];
                this.setState({
                    loading: false,
                    error: null,
                    user: data.user || this.state.user,
                    navigation,
                    activeNode: navigation.length ? navigation[0] : null,
                });
            } catch (error) {
                console.error('Bootstrap failed', error);
                this.setState({ loading: false, error: 'Kan intranet niet laden.' });
            }
        }

        selectNode(node) {
            this.setState({ activeNode: node });
        }

        render() {
            if (this.state.loading) {
                return h('div', { class: 'intranet-loader' }, 'Laden...');
            }
            if (this.state.error) {
                return h('div', { class: 'intranet-error' }, this.state.error);
            }
            return h('div', { class: 'intranet-shell' }, [
                this.renderNavigation(),
                this.renderMain(),
            ]);
        }

        renderNavigation() {
            const user = this.state.user || {};
            return h('aside', { class: 'intranet-nav' }, [
                h('div', { class: 'nav-user' }, [
                    h('div', { class: 'nav-user-name' }, user.nickname || 'Gebruiker'),
                    user.email ? h('div', { class: 'nav-user-email' }, user.email) : null,
                    h('a', { class: 'nav-logout', href: '/logout' }, 'Uitloggen'),
                ]),
                h('nav', { class: 'nav-tree' }, this.state.navigation.map((node) => {
                    const classes = ['nav-button'];
                    if (this.state.activeNode && this.state.activeNode.id === node.id) {
                        classes.push('active');
                    }
                    return h('button', {
                        key: node.id,
                        class: classes.join(' '),
                        onClick: () => this.selectNode(node),
                    }, node.label || node.id);
                })),
            ]);
        }

        renderMain() {
            const node = this.state.activeNode;
            if (!node) {
                return h('main', { class: 'intranet-main' }, [
                    h('section', { class: 'panel empty-state' }, [
                        h('h2', null, 'Welkom'),
                        h('p', null, 'Er zijn geen modules beschikbaar.'),
                    ]),
                ]);
            }

            if (node.view === 'settings.profile') {
                return this.renderProfile();
            }
            return this.renderHome();
        }

        renderHome() {
            const user = this.state.user || {};
            return h('main', { class: 'intranet-main' }, [
                h('section', { class: 'panel' }, [
                    h('h2', null, 'Welkom terug'),
                    h('p', null, `Fijn je te zien, ${user.nickname || 'collega'}!`),
                    h('p', null, 'Gebruik het menu om naar je instellingen te gaan of wacht op toekomstige modules.'),
                ]),
            ]);
        }

        renderProfile() {
            const user = this.state.user || {};
            return h('main', { class: 'intranet-main' }, [
                h('section', { class: 'panel profile-panel' }, [
                    h('h2', null, 'Profiel'),
                    h('dl', { class: 'profile-grid' }, [
                        h('dt', null, 'Naam'),
                        h('dd', null, user.nickname || '—'),
                        h('dt', null, 'E-mail'),
                        h('dd', null, user.email || '—'),
                        h('dt', null, 'Gebruikers-ID'),
                        h('dd', null, user.id != null ? String(user.id) : '—'),
                    ]),
                ]),
            ]);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const root = document.getElementById('intranet-root');
        if (!root) { return; }
        const data = window.INTRANET_BOOTSTRAP || {};
        const app = new IntranetApp({ data });
        app.mount(root);
    });
})();
