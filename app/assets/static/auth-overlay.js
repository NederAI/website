(function(global){
  class AuthOverlay extends DAB.BaseComponent {
    constructor(){
      super();
      this.state = { email: '', password: '', nickname: '', mode: 'login', message: '' };
    }

    updateField(e){
      this.state[e.target.name] = e.target.value;
    }

    async submitLogin(e){
      e.preventDefault();
      const {email, password} = this.state;
      try{
        const res = await DAB.api('/api/auth/login', {
          method: 'POST',
          headers: {'Content-Type':'application/json'},
          body: JSON.stringify({email, password})
        });
        if(res.error){
          this.state.message = res.error;
        } else {
          this.state.message = 'Logged in as ' + res.nickname;
        }
      }catch(err){
        this.state.message = 'Login failed';
      }
      this.update();
    }

    async submitRegister(e){
      e.preventDefault();
      const {email, password, nickname} = this.state;
      try{
        const res = await DAB.api('/api/auth/register', {
          method: 'POST',
          headers: {'Content-Type':'application/json'},
          body: JSON.stringify({email, password, nickname})
        });
        if(res.error){
          this.state.message = res.error;
        } else {
          this.state.message = 'Registered successfully';
        }
      }catch(err){
        this.state.message = 'Registration failed';
      }
      this.update();
    }

    close(){
      if(this.el) this.el.remove();
    }

    render(){
      const loginForm = DAB.h('form', {onSubmit: e => this.submitLogin(e)},
        DAB.h('h2', null, 'Login'),
        DAB.h('label', null, 'Email'),
        DAB.h('input', {type:'email', name:'email', required:true, value:this.state.email, onInput:e=>this.updateField(e)}),
        DAB.h('label', null, 'Password'),
        DAB.h('input', {type:'password', name:'password', required:true, value:this.state.password, onInput:e=>this.updateField(e)}),
        DAB.h('div', {class:'message'}, this.state.message),
        DAB.h('button', {type:'submit'}, 'Login')
      );

      const registerForm = DAB.h('form', {onSubmit: e => this.submitRegister(e)},
        DAB.h('h2', null, 'Register'),
        DAB.h('label', null, 'Email'),
        DAB.h('input', {type:'email', name:'email', required:true, value:this.state.email, onInput:e=>this.updateField(e)}),
        DAB.h('label', null, 'Password'),
        DAB.h('input', {type:'password', name:'password', required:true, value:this.state.password, onInput:e=>this.updateField(e)}),
        DAB.h('label', null, 'Nickname'),
        DAB.h('input', {type:'text', name:'nickname', required:true, value:this.state.nickname, onInput:e=>this.updateField(e)}),
        DAB.h('div', {class:'message'}, this.state.message),
        DAB.h('button', {type:'submit'}, 'Register')
      );

      const content = this.state.mode === 'login' ? loginForm : registerForm;

      return DAB.h('div', {class:'auth-overlay'},
        DAB.h('div', {class:'auth-modal'},
          DAB.h('button', {class:'close', onClick: () => this.close()}, '\u00D7'),
          DAB.h('div', {class:'tabs'},
            DAB.h('button', {onClick: () => {this.state.mode='login'; this.state.message=''; this.update();}}, 'Login'),
            DAB.h('button', {onClick: () => {this.state.mode='register'; this.state.message=''; this.update();}}, 'Register')
          ),
          content
        )
      );
    }
  }

  global.DAB.showAuthOverlay = function(){
    const overlay = new AuthOverlay();
    overlay.mount(document.body);
    return overlay;
  };
})(window);
