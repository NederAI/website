(function(){
  const { BaseComponent, h, api } = window.DAB || {};
  if (!BaseComponent) { return; }

  function formatCurrency(value) {
    const number = Number(value || 0);
    return new Intl.NumberFormat('nl-NL', {
      style: 'currency',
      currency: 'EUR',
      minimumFractionDigits: 2,
    }).format(number);
  }

  class RgsSelector extends BaseComponent {
    constructor(props) {
      super(props);
      this.state = {
        query: props.value || '',
        loading: false,
        items: [],
        message: 'Typ minimaal twee tekens om te zoeken.',
        selected: props.selected || null,
      };
      this.abort = null;
    }

    setState(next) {
      this.state = Object.assign({}, this.state, next);
      this.update();
    }

    render() {
      const { query, loading, items, message, selected } = this.state;
      return h('div', { class: 'rgs-panel' }, [
        h('label', { class: 'rgs-label' }, [
          'Zoeken in Referentie GrootboekSchema',
          h('input', {
            type: 'search',
            value: query,
            placeholder: 'Bijvoorbeeld B1 of "Loonsubsidie"',
            autocomplete: 'off',
            oninput: (event) => this.handleInput(event)
          })
        ]),
        h('div', { class: 'rgs-help' }, 'Selecteer een RGS-code om de juiste categorie te koppelen.'),
        loading ? h('div', { class: 'rgs-status' }, 'Bezig met zoeken...') : null,
        selected ? this.renderSelection(selected) : h('div', { class: 'rgs-selection rgs-selection--empty' }, 'Nog geen RGS geselecteerd.'),
        this.renderResults(items, message)
      ]);
    }

    renderSelection(item) {
      const badges = [];
      if (item.account_type) {
        badges.push(h('span', { class: 'badge type-' + item.account_type }, item.account_type));
      }
      badges.push(h('span', { class: 'badge ' + (item.is_postable ? 'badge-ok' : '') }, item.is_postable ? 'boekbaar' : 'aggregatie'));
      return h('div', { class: 'rgs-selection' }, [
        h('strong', null, item.code),
        item.title ? ' — ' + item.title : null,
        ...badges
      ]);
    }

    renderResults(items, message) {
      if (!this.state.query || this.state.query.trim().length < 2) {
        return null;
      }
      if (!items.length) {
        return h('div', { class: 'empty-state' }, message);
      }
      return h('ul', { class: 'rgs-results' }, items.map((item) => this.renderItem(item)));
    }

    renderItem(item) {
      const badges = [];
      if (item.account_type) {
        badges.push(h('span', { class: 'badge type-' + item.account_type }, item.account_type));
      }
      badges.push(h('span', { class: 'badge ' + (item.is_postable ? 'badge-ok' : '') }, item.is_postable ? 'boekbaar' : 'aggregatie'));
      return h('li', null, h('button', {
        type: 'button',
        class: 'rgs-option',
        onclick: () => this.select(item)
      }, [
        h('div', { class: 'rgs-option__header' }, [
          h('span', { class: 'rgs-option__code' }, item.code),
          ...badges
        ]),
        h('div', { class: 'rgs-option__title' }, item.title || 'Geen omschrijving'),
        item.function_label ? h('div', { class: 'rgs-option__meta' }, item.function_label) : null
      ]));
    }

    handleInput(event) {
      const query = event.target.value;
      this.setState({ query });
      if (this.debounce) {
        clearTimeout(this.debounce);
      }
      this.debounce = setTimeout(() => {
        const trimmed = query.trim();
        if (trimmed.length < 2) {
          this.setState({ items: [], message: 'Typ minimaal twee tekens om te zoeken.' });
          return;
        }
        this.search(trimmed);
      }, 250);
    }

    async search(term) {
      if (this.abort) {
        this.abort.abort();
      }
      this.abort = new AbortController();
      this.setState({ loading: true, message: 'Bezig met zoeken...' });
      try {
        const data = await api(`/api/intranet/rgs?q=${encodeURIComponent(term)}`, {
          signal: this.abort.signal,
        });
        const items = Array.isArray(data.items) ? data.items : [];
        this.setState({
          items,
          loading: false,
          message: items.length ? '' : 'Geen resultaten gevonden.',
        });
      } catch (error) {
        if (error.name === 'AbortError') {
          return;
        }
        console.error('RGS search failed', error);
        this.setState({ items: [], loading: false, message: 'Zoeken mislukt. Probeer opnieuw.' });
      }
    }

    select(item) {
      this.setState({ selected: item, items: [] });
      if (typeof this.props.onSelect === 'function') {
        this.props.onSelect(item);
      }
    }
  }

  class IntranetApp extends BaseComponent {
    constructor(props) {
      super(props);
      const data = props.data || {};
      this.state = {
        view: 'accounting',
        submitting: false,
        flash: null,
        accountForm: {
          code: '',
          name: '',
          rgs_code: '',
          account_type: '',
          currency: 'EUR',
          selectedRgs: null,
        },
        entryForm: {
          journal_code: 'MEM',
          entry_date: new Date().toISOString().slice(0, 10),
          reference: '',
          description: '',
          lines: [
            { account_code: '', amount: '', direction: 'debit' },
            { account_code: '', amount: '', direction: 'credit' },
          ],
        },
        user: data.user || null,
        accounts: data.accounts || [],
        trialBalance: data.trialBalance || [],
        entries: data.entries || [],
      };
    }

    setState(next) {
      this.state = Object.assign({}, this.state, next);
      this.update();
    }

    render() {
      if (!this.state.user) {
        return h('div', null, 'Geen toegang.');
      }
      const view = this.state.view;
      return h('main', { class: 'app-shell' }, [
        this.renderHeader(),
        this.state.flash ? h('div', { class: 'flash' }, this.state.flash) : null,
        view === 'accounting' ? this.renderAccounting() : this.renderSummary()
      ]);
    }

    renderHeader() {
      return h('header', { class: 'app-header' }, [
        h('div', null, [
          h('h1', null, 'Intranet'),
          h('div', { class: 'badge' }, this.state.user.nickname || this.state.user.email)
        ]),
        h('div', { class: 'app-nav' }, [
          h('button', {
            class: this.state.view === 'summary' ? 'active' : '',
            onclick: () => this.setState({ view: 'summary' })
          }, 'Overzicht'),
          h('button', {
            class: this.state.view === 'accounting' ? 'active' : '',
            onclick: () => this.setState({ view: 'accounting' })
          }, 'Boekhouding'),
          h('button', {
            onclick: () => window.location.href = '/logout'
          }, 'Afmelden')
        ])
      ]);
    }

    renderSummary() {
      const { accounts, entries, trialBalance } = this.state;
      const totalDebit = trialBalance.reduce((acc, row) => acc + Number(row.total_debit || 0), 0);
      const totalCredit = trialBalance.reduce((acc, row) => acc + Number(row.total_credit || 0), 0);
      return h('section', { class: 'dashboard-grid' }, [
        h('div', { class: 'card' }, [
          h('h2', null, 'Actieve rekeningen'),
          h('p', null, `${accounts.length} rekeningen in gebruik`)
        ]),
        h('div', { class: 'card' }, [
          h('h2', null, 'Journaal totalen'),
          h('p', null, 'Debet: ' + formatCurrency(totalDebit)),
          h('p', null, 'Credit: ' + formatCurrency(totalCredit))
        ]),
        h('div', { class: 'card' }, [
          h('h2', null, 'Laatste boeking'),
          entries.length ? h('p', null, `${entries[0].entry_date} • ${entries[0].journal_code}`) : h('p', null, 'Nog geen boekingen')
        ])
      ]);
    }

    renderAccounting() {
      return h('div', { class: 'dashboard-grid' }, [
        h('div', { class: 'card' }, [
          h('h2', null, 'Nieuwe grootboekrekening'),
          this.renderAccountForm()
        ]),
        h('div', { class: 'card' }, [
          h('h2', null, 'Nieuwe boeking'),
          this.renderEntryForm()
        ]),
        h('div', { class: 'card table-wrapper' }, [
          h('h2', null, 'Grootboek'),
          this.renderAccountsTable()
        ]),
        h('div', { class: 'card table-wrapper' }, [
          h('h2', null, 'Saldi'),
          this.renderTrialTable()
        ]),
        h('div', { class: 'card table-wrapper' }, [
          h('h2', null, 'Recente boekingen'),
          this.renderEntriesTable()
        ])
      ]);
    }

    renderAccountForm() {
      const form = this.state.accountForm;
      return h('form', {
        class: 'stack',
        onsubmit: (event) => this.submitAccount(event)
      }, [
        h('label', null, [
          'Rekeningcode',
          h('input', {
            name: 'code',
            required: true,
            value: form.code,
            oninput: (event) => this.updateAccountForm({ code: event.target.value })
          })
        ]),
        h('label', null, [
          'Naam',
          h('input', {
            name: 'name',
            required: true,
            value: form.name,
            oninput: (event) => this.updateAccountForm({ name: event.target.value })
          })
        ]),
        h('div', { class: 'rgs-panel' }, [
          h(RgsSelector, {
            value: form.rgs_code,
            selected: form.selectedRgs,
            onSelect: (item) => this.updateAccountForm({ rgs_code: item.code, selectedRgs: item })
          })
        ]),
        h('label', null, [
          'Rekeningtype',
          h('select', {
            name: 'account_type',
            value: form.account_type,
            oninput: (event) => this.updateAccountForm({ account_type: event.target.value })
          }, [
            h('option', { value: '' }, 'Automatisch (RGS)'),
            h('option', { value: 'asset' }, 'Asset'),
            h('option', { value: 'liability' }, 'Liability'),
            h('option', { value: 'equity' }, 'Equity'),
            h('option', { value: 'revenue' }, 'Revenue'),
            h('option', { value: 'expense' }, 'Expense'),
            h('option', { value: 'memo' }, 'Memo')
          ])
        ]),
        h('label', null, [
          'Valuta',
          h('input', {
            name: 'currency',
            value: form.currency,
            oninput: (event) => this.updateAccountForm({ currency: event.target.value || 'EUR' })
          })
        ]),
        h('button', {
          type: 'submit',
          class: 'primary',
          disabled: this.state.submitting
        }, this.state.submitting ? 'Bezig...' : 'Opslaan')
      ]);
    }

    renderEntryForm() {
      const form = this.state.entryForm;
      return h('form', {
        class: 'stack',
        onsubmit: (event) => this.submitEntry(event)
      }, [
        h('label', null, [
          'Journaalcode',
          h('input', {
            name: 'journal_code',
            value: form.journal_code,
            required: true,
            oninput: (event) => this.updateEntryForm({ journal_code: event.target.value })
          })
        ]),
        h('label', null, [
          'Datum',
          h('input', {
            type: 'date',
            name: 'entry_date',
            value: form.entry_date,
            required: true,
            oninput: (event) => this.updateEntryForm({ entry_date: event.target.value })
          })
        ]),
        h('label', null, [
          'Referentie',
          h('input', {
            name: 'reference',
            value: form.reference,
            oninput: (event) => this.updateEntryForm({ reference: event.target.value })
          })
        ]),
        h('label', null, [
          'Omschrijving',
          h('input', {
            name: 'description',
            value: form.description,
            oninput: (event) => this.updateEntryForm({ description: event.target.value })
          })
        ]),
        this.renderEntryLines(form.lines),
        h('button', {
          type: 'submit',
          class: 'primary',
          disabled: this.state.submitting
        }, this.state.submitting ? 'Bezig...' : 'Boeking toevoegen')
      ]);
    }

    renderEntryLines(lines) {
      return h('div', { class: 'stack' }, lines.map((line, index) => h('div', { class: 'stack' }, [
        h('label', null, [
          `Rekening (${line.direction === 'debit' ? 'debet' : 'credit'})`,
          h('input', {
            value: line.account_code,
            required: true,
            oninput: (event) => this.updateEntryLine(index, { account_code: event.target.value })
          })
        ]),
        h('label', null, [
          'Bedrag',
          h('input', {
            type: 'number',
            min: '0',
            step: '0.01',
            value: line.amount,
            required: true,
            oninput: (event) => this.updateEntryLine(index, { amount: event.target.value })
          })
        ])
      ])));
    }

    renderAccountsTable() {
      const accounts = this.state.accounts;
      if (!accounts.length) {
        return h('div', { class: 'empty-state' }, 'Nog geen rekeningen.');
      }
      return h('table', { class: 'data' }, [
        h('thead', null, h('tr', null, [
          h('th', null, 'Code'),
          h('th', null, 'Naam'),
          h('th', null, 'Type'),
          h('th', null, 'RGS'),
          h('th', null, 'Valuta')
        ])),
        h('tbody', null, accounts.map((account) => h('tr', null, [
          h('td', null, account.code),
          h('td', null, account.name),
          h('td', null, account.account_type || '—'),
          h('td', null, account.rgs_code || '—'),
          h('td', null, account.currency || 'EUR')
        ])))
      ]);
    }

    renderTrialTable() {
      const rows = this.state.trialBalance;
      if (!rows.length) {
        return h('div', { class: 'empty-state' }, 'Nog geen saldi beschikbaar.');
      }
      return h('table', { class: 'data' }, [
        h('thead', null, h('tr', null, [
          h('th', null, 'Code'),
          h('th', null, 'Naam'),
          h('th', null, 'Debet'),
          h('th', null, 'Credit'),
          h('th', null, 'Saldo')
        ])),
        h('tbody', null, rows.map((row) => h('tr', null, [
          h('td', null, row.account_code),
          h('td', null, row.account_name),
          h('td', { class: 'num' }, formatCurrency(row.total_debit)),
          h('td', { class: 'num' }, formatCurrency(row.total_credit)),
          h('td', { class: 'num' }, formatCurrency(row.balance))
        ])))
      ]);
    }

    renderEntriesTable() {
      const entries = this.state.entries;
      if (!entries.length) {
        return h('div', { class: 'empty-state' }, 'Nog geen boekingen.');
      }
      return h('table', { class: 'data' }, [
        h('thead', null, h('tr', null, [
          h('th', null, 'Datum'),
          h('th', null, 'Journaal'),
          h('th', null, 'Referentie'),
          h('th', null, 'Status'),
          h('th', null, 'Omschrijving')
        ])),
        h('tbody', null, entries.map((entry) => h('tr', null, [
          h('td', null, entry.entry_date || '—'),
          h('td', null, entry.journal_code || '—'),
          h('td', null, entry.reference || '—'),
          h('td', null, entry.status || '—'),
          h('td', null, entry.description || '—')
        ])))
      ]);
    }

    updateAccountForm(patch) {
      this.state.accountForm = Object.assign({}, this.state.accountForm, patch);
      this.update();
    }

    updateEntryForm(patch) {
      this.state.entryForm = Object.assign({}, this.state.entryForm, patch);
      this.update();
    }

    updateEntryLine(index, patch) {
      const lines = this.state.entryForm.lines.slice();
      lines[index] = Object.assign({}, lines[index], patch);
      this.updateEntryForm({ lines });
    }

    async submitAccount(event) {
      event.preventDefault();
      if (this.state.submitting) { return; }
      const form = this.state.accountForm;
      const payload = {
        code: form.code,
        name: form.name,
        rgs_code: form.rgs_code,
        account_type: form.account_type,
        currency: form.currency || 'EUR',
      };
      this.setState({ submitting: true, flash: null });
      try {
        const response = await api('/accounting/create-account', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: JSON.stringify(payload)
        });
        if (!response.success) {
          throw new Error(response.error || 'Opslaan mislukt');
        }
        const account = response.account;
        this.state.accounts = this.state.accounts.filter((item) => item.code !== account.code).concat([account]).sort((a, b) => a.code.localeCompare(b.code));
        this.updateAccountForm({ code: '', name: '', rgs_code: '', account_type: '', selectedRgs: null });
        this.setState({ flash: 'Rekening opgeslagen.', submitting: false });
        this.refreshSnapshot();
      } catch (error) {
        console.error(error);
        this.setState({ flash: error.message || 'Opslaan mislukt.', submitting: false });
      }
    }

    async submitEntry(event) {
      event.preventDefault();
      if (this.state.submitting) { return; }
      const form = this.state.entryForm;
      const payload = {
        journal_code: form.journal_code,
        entry_date: form.entry_date,
        reference: form.reference,
        description: form.description,
        lines: form.lines
      };
      this.setState({ submitting: true, flash: null });
      try {
        const response = await api('/accounting/create-entry', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: JSON.stringify(payload)
        });
        if (!response.success) {
          throw new Error(response.error || 'Boeking mislukt.');
        }
        this.setState({ flash: 'Boeking aangemaakt.', submitting: false });
        this.updateEntryForm({
          journal_code: 'MEM',
          entry_date: new Date().toISOString().slice(0, 10),
          reference: '',
          description: '',
          lines: [
            { account_code: '', amount: '', direction: 'debit' },
            { account_code: '', amount: '', direction: 'credit' }
          ]
        });
        this.refreshSnapshot();
      } catch (error) {
        console.error(error);
        this.setState({ flash: error.message || 'Boeking mislukt.', submitting: false });
      }
    }

    async refreshSnapshot() {
      try {
        const data = await api('/api/intranet/snapshot');
        this.setState({
          accounts: data.accounts || this.state.accounts,
          trialBalance: data.trialBalance || this.state.trialBalance,
          entries: data.entries || this.state.entries,
        });
      } catch (error) {
        console.error('Kon gegevens niet verversen', error);
      }
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('app-root');
    if (!root) { return; }
    const data = window.INTRANET_DATA || {};
    const app = new IntranetApp({ data });
    app.mount(root);
  });
})();
