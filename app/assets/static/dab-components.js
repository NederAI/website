(function(global){
  function h(tag, props, ...children){
    const el = document.createElement(tag);
    props = props || {};
    Object.entries(props).forEach(([key, value]) => {
      if(key.startsWith('on') && typeof value === 'function'){
        el.addEventListener(key.slice(2).toLowerCase(), value);
      } else if(key === 'style' && typeof value === 'object') {
        Object.assign(el.style, value);
      } else {
        el.setAttribute(key, value);
      }
    });
    children.flat().forEach(child => {
      if(child == null) return;
      if(typeof child === 'string' || typeof child === 'number'){
        el.appendChild(document.createTextNode(child));
      } else {
        el.appendChild(child);
      }
    });
    return el;
  }

  class BaseComponent {
    constructor(props){
      this.props = props || {};
      this.state = {};
      this.el = null;
    }

    render(){
      return h('div');
    }

    mount(root){
      this.el = this.render();
      if(root) root.appendChild(this.el);
    }

    update(){
      if(!this.el) return;
      const newEl = this.render();
      this.el.replaceWith(newEl);
      this.el = newEl;
    }
  }

  async function api(url, options){
    const res = await fetch(url, options);
    const type = res.headers.get('content-type') || '';
    if(type.includes('application/json')){
      return res.json();
    }
    return res.text();
  }

  global.DAB = {
    h,
    BaseComponent,
    api
  };
})(window);
