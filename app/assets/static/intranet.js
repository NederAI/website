(function () {
    const { BaseComponent, h, api } = window.DAB || {};
    if (!BaseComponent) { return; }

    function formatCurrency(value, currency = 'EUR') {
        const number = Number(value || 0);
        return new Intl.NumberFormat('nl-NL', {
            style: 'currency',
            currency,
            minimumFractionDigits: 2,
        }).format(number);
    }

    class IntranetShell extends BaseComponent {
        constructor(props) {
            super(props);
            const bootstrap = props.data || {};
            this.nodeIndex = {};
            this.state = {
                loading: true,
                error: null,
                navigation: [],
                expanded: {},
                activeNode: null,
                activeView: null,
                activeContext: {},
                user: bootstrap.user || null,
                accounting: this.createAccountingState(),
            };
        }

        createAccountingState() {
            return {
                activeOrg: null,
                organization: null,
                snapshot: null,
                loading: false,
                error: null,
                flash: null,
                accountForm: {
                    code: '',
                    name: '',
                    type: 'asset',
                    currency: '',
                    rgs_code: '',
                },
                entryForm: {
                    entry_date: new Date().toISOString().slice(0, 10),
                    reference: '',
                    description: '',
                    status: 'draft',
                    lines: [
                        { direction: 'debit', account_code: '', amount: '' },
                        { direction: 'credit', account_code: '', amount: '' }
                    ],
                },
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

        setAccountingState(patch) {
            const next = Object.assign({}, this.state.accounting, patch);
            this.state = Object.assign({}, this.state, { accounting: next });
            this.update();
        }

        async bootstrap() {
            try {
                const data = await api('/api/intranet/bootstrap');
                if (data && data.error) {
                    throw new Error(data.error);
                }
                const navigation = data.navigation || [];
                this.nodeIndex = {};
                this.indexNavigation(navigation, null);
                const expanded = {};
                navigation.forEach(node => { expanded[node.id] = true; });
                this.setState({
                    loading: false,
                    error: null,
                    navigation,
                    expanded,
                    user: data.user || this.state.user,
                    organizations: data.organizations || [],
                });
                const defaultNode = this.findDefaultNode(navigation);
                if (defaultNode) {
                    this.activateNode(defaultNode);
                }
            } catch (error) {
                console.error('Bootstrap error', error);
                this.setState({ loading: false, error: 'Kon intranetgegevens niet laden.' });
            }
        }

        indexNavigation(nodes, parentId) {
            nodes.forEach(node => {
                this.nodeIndex[node.id] = { node, parent: parentId };
                if (Array.isArray(node.children) && node.children.length) {
                    this.indexNavigation(node.children, node.id);
                }
            });
        }

        findDefaultNode(nodes) {
            for (const node of nodes) {
                if (node.view) {
                    return node;
                }
                if (Array.isArray(node.children) && node.children.length) {
                    const child = this.findDefaultNode(node.children);
                    if (child) {
                        return child;
                    }
                }
            }
            return null;
        }

        expandPath(nodeId, expanded) {
            let cursor = nodeId;
            while (cursor && this.nodeIndex[cursor] && this.nodeIndex[cursor].parent) {
                const parent = this.nodeIndex[cursor].parent;
                expanded[parent] = true;
                cursor = parent;
            }
        }

        toggleExpanded(nodeId, forceOpen = false) {
            const expanded = Object.assign({}, this.state.expanded);
            if (forceOpen) {
                expanded[nodeId] = true;
            } else {
                expanded[nodeId] = !expanded[nodeId];
            }
            this.setState({ expanded });
        }

        handleNavClick(node) {
            const hasChildren = Array.isArray(node.children) && node.children.length > 0;
            if (hasChildren && (!node.view || node.view === null)) {
                this.toggleExpanded(node.id);
                return;
            }
            if (hasChildren) {
                this.toggleExpanded(node.id, true);
            }
            if (node.view) {
                this.activateNode(node);
            }
        }

        activateNode(node) {
            const expanded = Object.assign({}, this.state.expanded);
            this.expandPath(node.id, expanded);
            this.state = Object.assign({}, this.state, {
                expanded,
                activeNode: node,
                activeView: node.view || null,
                activeContext: node.context || {},
            });
            this.update();
            if (node.view === 'accounting.dashboard') {
                this.loadAccounting(node.context || {});
            }
        }

        async loadAccounting(context) {
            const orgCode = context.org_code || null;
            if (!orgCode) {
                this.setAccountingState({ error: 'Geen organisatie geselecteerd.' });
                return;
            }
            const current = this.state.accounting;
            if (current.activeOrg === orgCode && current.loading) {
                return;
            }
            this.setAccountingState({
                activeOrg: orgCode,
                organization: null,
                snapshot: null,
                loading: true,
                error: null,
                flash: null,
            });
            try {
                const data = await api(`/api/intranet/accounting/organizations/${encodeURIComponent(orgCode)}/snapshot`);
                if (data && data.error) {
                    throw new Error(data.error);
                }
                this.setAccountingState({
                    loading: false,
                    organization: data.organization || null,
                    snapshot: data,
                    error: null,
                });
            } catch (error) {
                console.error('Accounting snapshot error', error);
                this.setAccountingState({
                    loading: false,
                    error: 'Kon administratiegegevens niet laden.',
                });
            }
        }

        updateAccountForm(patch) {
            const form = Object.assign({}, this.state.accounting.accountForm, patch);
            this.setAccountingState({ accountForm: form });
        }

        updateEntryForm(patch) {
            const form = Object.assign({}, this.state.accounting.entryForm, patch);
            this.setAccountingState({ entryForm: form });
        }

        updateEntryLine(index, patch) {
            const form = this.state.accounting.entryForm;
            const lines = form.lines.slice();
            lines[index] = Object.assign({}, lines[index], patch);
            this.updateEntryForm({ lines });
        }

        addEntryLine(direction = 'debit') {
            const form = this.state.accounting.entryForm;
            const lines = form.lines.slice();
            lines.push({ direction, account_code: '', amount: '' });
            this.updateEntryForm({ lines });
        }

        removeEntryLine(index) {
            const form = this.state.accounting.entryForm;
            if (form.lines.length <= 2) { return; }
            const lines = form.lines.slice();
            lines.splice(index, 1);
            this.updateEntryForm({ lines });
        }

        async submitAccount(event) {
            event.preventDefault();
            const accounting = this.state.accounting;
            if (!accounting.activeOrg) { return; }
            const payload = Object.assign({}, accounting.accountForm);
            if (!payload.code || !payload.name) {
                this.setAccountingState({ flash: 'Vul code en naam in.' });
                return;
            }
            try {
                const response = await api(`/api/intranet/accounting/organizations/${encodeURIComponent(accounting.activeOrg)}/accounts`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(payload)
                });
                if (response && response.error) {
                    throw new Error(response.error);
                }
                this.setAccountingState({
                    flash: 'Rekening opgeslagen.',
                    accountForm: Object.assign({}, this.state.accounting.accountForm, {
                        code: '',
                        name: '',
                        rgs_code: '',
                    }),
                });
                this.loadAccounting({ org_code: accounting.activeOrg });
            } catch (error) {
                console.error('Account opslaan', error);
                this.setAccountingState({ flash: error.message || 'Opslaan mislukt.' });
            }
        }

        async submitEntry(event) {
            event.preventDefault();
            const accounting = this.state.accounting;
            const orgCode = accounting.activeOrg;
            if (!orgCode) { return; }
            const form = accounting.entryForm;
            const lines = form.lines
                .map(line => ({
                    direction: line.direction,
                    account_code: (line.account_code || '').toUpperCase(),
                    amount: parseFloat(line.amount)
                }))
                .filter(line => line.account_code && !isNaN(line.amount));
            if (lines.length < 2) {
                this.setAccountingState({ flash: 'Minimaal twee ingevulde regels nodig.' });
                return;
            }
            const payload = {
                entry_date: form.entry_date,
                reference: form.reference,
                description: form.description,
                status: form.status,
            };
            try {
                const response = await api(`/api/intranet/accounting/organizations/${encodeURIComponent(orgCode)}/entries`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(Object.assign({}, payload, { lines }))
                });
                if (response && response.error) {
                    throw new Error(response.error);
                }
                this.setAccountingState({
                    flash: 'Boeking aangemaakt.',
                    entryForm: Object.assign({}, this.createAccountingState().entryForm),
                });
                this.loadAccounting({ org_code: orgCode });
            } catch (error) {
                console.error('Boeking mislukt', error);
                this.setAccountingState({ flash: error.message || 'Boeking mislukt.' });
            }
        }

        render() {
            if (this.state.loading) {
                return h('div', { class: 'intranet-loader' }, 'Intranet wordt geladen...');
            }
            if (this.state.error) {
                return h('div', { class: 'intranet-error' }, this.state.error);
            }
            return h('div', { class: 'intranet-shell' }, [
                h('aside', { class: 'intranet-nav' }, [
                    this.renderUserPanel(),
                    this.renderNavigation(),
                ]),
                h('main', { class: 'intranet-main' }, [
                    this.renderMainContent()
                ])
            ]);
        }

        renderUserPanel() {
            const user = this.state.user;
            if (!user) { return null; }
            return h('div', { class: 'nav-user' }, [
                h('div', { class: 'nav-user-name' }, user.nickname || user.email || 'Gebruiker'),
                h('div', { class: 'nav-user-email' }, user.email),
                h('a', { class: 'nav-logout', href: '/logout' }, 'Uitloggen')
            ]);
        }

        renderNavigation() {
            if (!this.state.navigation.length) {
                return h('p', { class: 'nav-empty' }, 'Geen navigatie beschikbaar.');
            }
            return h('nav', { class: 'nav-tree' }, this.renderNavGroup(this.state.navigation, 0));
        }

        renderNavGroup(nodes, depth) {
            return h('ul', { class: 'nav-level nav-depth-' + depth },
                nodes.map(node => this.renderNavItem(node, depth))
            );
        }

        renderNavItem(node, depth) {
            const hasChildren = Array.isArray(node.children) && node.children.length > 0;
            const isExpanded = !!this.state.expanded[node.id];
            const isActive = this.state.activeNode && this.state.activeNode.id === node.id;
            const btnClasses = ['nav-button'];
            if (hasChildren) { btnClasses.push('has-children'); }
            if (isExpanded) { btnClasses.push('expanded'); }
            if (isActive) { btnClasses.push('active'); }
            return h('li', { class: 'nav-item nav-depth-' + depth }, [
                h('button', {
                    class: btnClasses.join(' '),
                    onClick: () => this.handleNavClick(node)
                }, [
                    hasChildren ? h('span', { class: 'nav-caret' }, isExpanded ? '?' : '?') : null,
                    h('span', { class: 'nav-label' }, node.label)
                ]),
                hasChildren && isExpanded ? this.renderNavGroup(node.children, depth + 1) : null
            ]);
        }

        renderMainContent() {
            if (!this.state.activeView) {
                return h('section', { class: 'panel empty-state' }, [
                    h('h2', null, 'Welkom'),
                    h('p', null, 'Kies een onderdeel in het menu om te starten.')
                ]);
            }
            if (this.state.activeView === 'accounting.dashboard') {
                return this.renderAccounting();
            }
            if (this.state.activeView === 'settings.profile') {
                return this.renderProfile();
            }
            return h('section', { class: 'panel empty-state' }, [
                h('h2', null, 'Nog niet beschikbaar'),
                h('p', null, 'Deze module is in ontwikkeling.')
            ]);
        }

        renderProfile() {
            const user = this.state.user || {};
            return h('section', { class: 'panel profile-panel' }, [
                h('h2', null, 'Profiel'),
                h('dl', { class: 'profile-grid' }, [
                    h('dt', null, 'Naam'),
                    h('dd', null, user.nickname || '—'),
                    h('dt', null, 'E-mail'),
                    h('dd', null, user.email || '—')
                ])
            ]);
        }

        renderAccounting() {
            const state = this.state.accounting;
            if (state.loading) {
                return h('section', { class: 'panel' }, [
                    h('h2', null, 'Administratie'),
                    h('p', null, 'Gegevens worden geladen...')
                ]);
            }
            if (state.error) {
                return h('section', { class: 'panel error' }, [
                    h('h2', null, 'Administratie'),
                    h('p', null, state.error)
                ]);
            }
            const snapshot = state.snapshot || {};
            const organization = snapshot.organization || state.organization || {};
            return h('div', { class: 'accounting-grid' }, [
                h('section', { class: 'panel accounting-header' }, [
                    h('div', { class: 'panel-header' }, [
                        h('h2', null, organization.name || 'Administratie'),
                        h('span', { class: 'panel-subtitle' }, organization.code ? `Code: ${organization.code}` : '')
                    ]),
                    state.flash ? h('p', { class: 'flash' }, state.flash) : null
                ]),
                this.renderAccountForm(),
                this.renderEntryForm(),
                this.renderAccountsTable(snapshot.accounts || []),
                this.renderTrialBalance(snapshot.trialBalance || []),
                this.renderEntries(snapshot.entries || [])
            ]);
        }

        renderAccountForm() {
            const form = this.state.accounting.accountForm;
            return h('section', { class: 'panel form-panel' }, [
                h('h3', null, 'Nieuwe grootboekrekening'),
                h('form', {
                    class: 'form-grid',
                    onSubmit: (event) => this.submitAccount(event)
                }, [
                    h('label', null, [
                        'Code',
                        h('input', {
                            type: 'text',
                            value: form.code,
                            onInput: (event) => this.updateAccountForm({ code: event.target.value.toUpperCase() })
                        })
                    ]),
                    h('label', null, [
                        'Naam',
                        h('input', {
                            type: 'text',
                            value: form.name,
                            onInput: (event) => this.updateAccountForm({ name: event.target.value })
                        })
                    ]),
                    h('label', null, [
                        'Type',
                        h('select', {
                            value: form.type,
                            onChange: (event) => this.updateAccountForm({ type: event.target.value })
                        }, [
                            h('option', { value: 'asset' }, 'Activa'),
                            h('option', { value: 'liability' }, 'Passiva'),
                            h('option', { value: 'equity' }, 'Vermogen'),
                            h('option', { value: 'revenue' }, 'Opbrengst'),
                            h('option', { value: 'expense' }, 'Kosten')
                        ])
                    ]),
                    h('label', null, [
                        'Valuta',
                        h('input', {
                            type: 'text',
                            value: form.currency,
                            placeholder: 'Bijv. EUR',
                            onInput: (event) => this.updateAccountForm({ currency: event.target.value.toUpperCase() })
                        })
                    ]),
                    h('label', { class: 'full-span' }, [
                        'RGS-code (optioneel)',
                        h('input', {
                            type: 'text',
                            value: form.rgs_code,
                            onInput: (event) => this.updateAccountForm({ rgs_code: event.target.value.toUpperCase() })
                        })
                    ]),
                    h('div', { class: 'form-actions full-span' }, [
                        h('button', { type: 'submit' }, 'Opslaan')
                    ])
                ])
            ]);
        }

        renderEntryForm() {
            const form = this.state.accounting.entryForm;
            return h('section', { class: 'panel form-panel' }, [
                h('h3', null, 'Nieuwe boeking'),
                h('form', {
                    class: 'form-grid',
                    onSubmit: (event) => this.submitEntry(event)
                }, [
                    h('label', null, [
                        'Datum',
                        h('input', {
                            type: 'date',
                            value: form.entry_date,
                            onInput: (event) => this.updateEntryForm({ entry_date: event.target.value })
                        })
                    ]),
                    h('label', null, [
                        'Referentie',
                        h('input', {
                            type: 'text',
                            value: form.reference,
                            onInput: (event) => this.updateEntryForm({ reference: event.target.value })
                        })
                    ]),
                    h('label', { class: 'full-span' }, [
                        'Omschrijving',
                        h('textarea', {
                            value: form.description,
                            onInput: (event) => this.updateEntryForm({ description: event.target.value })
                        })
                    ]),
                    h('div', { class: 'entry-lines full-span' }, [
                        h('header', { class: 'entry-lines-header' }, [
                            h('span', null, 'Regels'),
                            h('div', { class: 'entry-lines-actions' }, [
                                h('button', {
                                    type: 'button',
                                    onClick: () => this.addEntryLine('debit')
                                }, 'Debitregel'),
                                h('button', {
                                    type: 'button',
                                    onClick: () => this.addEntryLine('credit')
                                }, 'Creditregel')
                            ])
                        ]),
                        h('table', { class: 'entry-lines-table' }, [
                            h('thead', null, [
                                h('tr', null, [
                                    h('th', null, 'Type'),
                                    h('th', null, 'Rekeningcode'),
                                    h('th', null, 'Bedrag'),
                                    h('th', null, '')
                                ])
                            ]),
                            h('tbody', null, form.lines.map((line, index) => (
                                h('tr', { key: index }, [
                                    h('td', null, [
                                        h('select', {
                                            value: line.direction,
                                            onChange: (event) => this.updateEntryLine(index, { direction: event.target.value })
                                        }, [
                                            h('option', { value: 'debit' }, 'Debet'),
                                            h('option', { value: 'credit' }, 'Credit')
                                        ])
                                    ]),
                                    h('td', null, [
                                        h('input', {
                                            type: 'text',
                                            value: line.account_code,
                                            onInput: (event) => this.updateEntryLine(index, { account_code: event.target.value.toUpperCase() })
                                        })
                                    ]),
                                    h('td', null, [
                                        h('input', {
                                            type: 'number',
                                            step: '0.01',
                                            value: line.amount,
                                            onInput: (event) => this.updateEntryLine(index, { amount: event.target.value })
                                        })
                                    ]),
                                    h('td', null, [
                                        h('button', {
                                            type: 'button',
                                            class: 'remove-line',
                                            onClick: () => this.removeEntryLine(index)
                                        }, '×')
                                    ])
                                ])
                            )))
                        ])
                    ]),
                    h('div', { class: 'form-actions full-span' }, [
                        h('button', { type: 'submit' }, 'Boeking opslaan')
                    ])
                ])
            ]);
        }

        renderAccountsTable(accounts) {
            if (!accounts.length) {
                return h('section', { class: 'panel data-panel' }, [
                    h('h3', null, 'Grootboekrekeningen'),
                    h('p', null, 'Nog geen rekeningen aanwezig.')
                ]);
            }
            return h('section', { class: 'panel data-panel' }, [
                h('h3', null, 'Grootboekrekeningen'),
                h('table', { class: 'data-table' }, [
                    h('thead', null, [
                        h('tr', null, [
                            h('th', null, 'Code'),
                            h('th', null, 'Naam'),
                            h('th', null, 'Type'),
                            h('th', null, 'Valuta')
                        ])
                    ]),
                    h('tbody', null, accounts.map(account => (
                        h('tr', { key: account.id }, [
                            h('td', null, account.code),
                            h('td', null, account.name),
                            h('td', null, account.type),
                            h('td', null, account.currency || 'EUR')
                        ])
                    )))
                ])
            ]);
        }

        renderTrialBalance(rows) {
            if (!rows.length) {
                return h('section', { class: 'panel data-panel' }, [
                    h('h3', null, 'Proefbalans'),
                    h('p', null, 'Nog geen mutaties geboekt.')
                ]);
            }
            const currency = rows[0].currency || 'EUR';
            return h('section', { class: 'panel data-panel' }, [
                h('h3', null, 'Proefbalans'),
                h('table', { class: 'data-table' }, [
                    h('thead', null, [
                        h('tr', null, [
                            h('th', null, 'Rekening'),
                            h('th', null, 'Debet'),
                            h('th', null, 'Credit'),
                            h('th', null, 'Saldo')
                        ])
                    ]),
                    h('tbody', null, rows.map(row => (
                        h('tr', { key: row.account_id }, [
                            h('td', null, `${row.code} · ${row.name}`),
                            h('td', null, formatCurrency(row.total_debit, currency)),
                            h('td', null, formatCurrency(row.total_credit, currency)),
                            h('td', null, formatCurrency(row.balance, currency))
                        ])
                    )))
                ])
            ]);
        }

        renderEntries(entries) {
            if (!entries.length) {
                return h('section', { class: 'panel data-panel' }, [
                    h('h3', null, 'Laatste boekingen'),
                    h('p', null, 'Nog geen boekingen beschikbaar.')
                ]);
            }
            return h('section', { class: 'panel data-panel' }, [
                h('h3', null, 'Laatste boekingen'),
                h('table', { class: 'data-table' }, [
                    h('thead', null, [
                        h('tr', null, [
                            h('th', null, 'Datum'),
                            h('th', null, 'Referentie'),
                            h('th', null, 'Status'),
                            h('th', null, 'Debet'),
                            h('th', null, 'Credit')
                        ])
                    ]),
                    h('tbody', null, entries.map(entry => (
                        h('tr', { key: entry.id }, [
                            h('td', null, entry.entry_date),
                            h('td', null, entry.reference || '—'),
                            h('td', null, entry.status),
                            h('td', null, formatCurrency(entry.total_debit, entry.currency || 'EUR')),
                            h('td', null, formatCurrency(entry.total_credit, entry.currency || 'EUR'))
                        ])
                    )))
                ])
            ]);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const root = document.getElementById('intranet-root');
        if (!root) { return; }
        const data = window.INTRANET_BOOTSTRAP || {};
        const app = new IntranetShell({ data });
        app.mount(root);
    });
})();