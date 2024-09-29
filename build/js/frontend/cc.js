(()=>{var e={844:(e,t,n)=>{e.exports=n(200)},200:(e,t,n)=>{"use strict";var o,r=(o=n(609))&&"object"==typeof o&&"default"in o?o.default:o,a=n(795);function s(){return(s=Object.assign||function(e){for(var t=1;t<arguments.length;t++){var n=arguments[t];for(var o in n)Object.prototype.hasOwnProperty.call(n,o)&&(e[o]=n[o])}return e}).apply(this,arguments)}function i(e){if(void 0===e)throw new ReferenceError("this hasn't been initialised - super() hasn't been called");return e}var l=function(e,t,n,o,r,a,s,i){if(!e){var l;if(void 0===t)l=new Error("Minified exception occurred; use the non-minified dev environment for the full error message and additional helpful warnings.");else{var u=[n,o,r,a,s,i],c=0;(l=new Error(t.replace(/%s/g,(function(){return u[c++]})))).name="Invariant Violation"}throw l.framesToPop=1,l}};function u(e,t,n){if("selectionStart"in e&&"selectionEnd"in e)e.selectionStart=t,e.selectionEnd=n;else{var o=e.createTextRange();o.collapse(!0),o.moveStart("character",t),o.moveEnd("character",n-t),o.select()}}var c={9:"[0-9]",a:"[A-Za-z]","*":"[A-Za-z0-9]"};function p(e,t,n){var o="",r="",a=null,s=[];if(void 0===t&&(t="_"),null==n&&(n=c),!e||"string"!=typeof e)return{maskChar:t,formatChars:n,mask:null,prefix:null,lastEditablePosition:null,permanents:[]};var i=!1;return e.split("").forEach((function(e){i=!i&&"\\"===e||(i||!n[e]?(s.push(o.length),o.length===s.length-1&&(r+=e)):a=o.length+1,o+=e,!1)})),{maskChar:t,formatChars:n,prefix:r,mask:o,lastEditablePosition:a,permanents:s}}function f(e,t){return-1!==e.permanents.indexOf(t)}function h(e,t,n){var o=e.mask,r=e.formatChars;if(!n)return!1;if(f(e,t))return o[t]===n;var a=r[o[t]];return new RegExp(a).test(n)}function d(e,t){return t.split("").every((function(t,n){return f(e,n)||!h(e,n,t)}))}function v(e,t){var n=e.maskChar,o=e.prefix;if(!n){for(;t.length>o.length&&f(e,t.length-1);)t=t.slice(0,t.length-1);return t.length}for(var r=o.length,a=t.length;a>=o.length;a--){var s=t[a];if(!f(e,a)&&h(e,a,s)){r=a+1;break}}return r}function m(e,t){return v(e,t)===e.mask.length}function g(e,t){var n=e.maskChar,o=e.mask,r=e.prefix;if(!n){for((t=k(e,"",t,0)).length<r.length&&(t=r);t.length<o.length&&f(e,t.length);)t+=o[t.length];return t}if(t)return k(e,g(e,""),t,0);for(var a=0;a<o.length;a++)f(e,a)?t+=o[a]:t+=n;return t}function k(e,t,n,o){var r=e.mask,a=e.maskChar,s=e.prefix,i=n.split(""),l=m(e,t);return!a&&o>t.length&&(t+=r.slice(t.length,o)),i.every((function(n){for(;c=n,f(e,u=o)&&c!==r[u];){if(o>=t.length&&(t+=r[o]),i=n,a&&f(e,o)&&i===a)return!0;if(++o>=r.length)return!1}var i,u,c;return!h(e,o,n)&&n!==a||(o<t.length?t=a||l||o<s.length?t.slice(0,o)+n+t.slice(o+1):(t=t.slice(0,o)+n+t.slice(o),g(e,t)):a||(t+=n),++o<r.length)})),t}function w(e,t){for(var n=e.mask,o=t;o<n.length;++o)if(!f(e,o))return o;return null}function S(e){return e||0===e?e+"":""}function C(e){return"function"==typeof e}function O(){return window.cancelAnimationFrame||window.webkitCancelRequestAnimationFrame||window.webkitCancelAnimationFrame||window.mozCancelAnimationFrame}function b(e){return(O()?window.requestAnimationFrame||window.webkitRequestAnimationFrame||window.mozRequestAnimationFrame:function(){return setTimeout(e,1e3/60)})(e)}function y(e){(O()||clearTimeout)(e)}var x=function(e){function t(t){var n=e.call(this,t)||this;n.focused=!1,n.mounted=!1,n.previousSelection=null,n.selectionDeferId=null,n.saveSelectionLoopDeferId=null,n.saveSelectionLoop=function(){n.previousSelection=n.getSelection(),n.saveSelectionLoopDeferId=b(n.saveSelectionLoop)},n.runSaveSelectionLoop=function(){null===n.saveSelectionLoopDeferId&&n.saveSelectionLoop()},n.stopSaveSelectionLoop=function(){null!==n.saveSelectionLoopDeferId&&(y(n.saveSelectionLoopDeferId),n.saveSelectionLoopDeferId=null,n.previousSelection=null)},n.getInputDOMNode=function(){if(!n.mounted)return null;var e=a.findDOMNode(i(i(n))),t="undefined"!=typeof window&&e instanceof window.Element;if(e&&!t)return null;if("INPUT"!==e.nodeName&&(e=e.querySelector("input")),!e)throw new Error("react-input-mask: inputComponent doesn't contain input node");return e},n.getInputValue=function(){var e=n.getInputDOMNode();return e?e.value:null},n.setInputValue=function(e){var t=n.getInputDOMNode();t&&(n.value=e,t.value=e)},n.setCursorToEnd=function(){var e=v(n.maskOptions,n.value),t=w(n.maskOptions,e);null!==t&&n.setCursorPosition(t)},n.setSelection=function(e,t,o){void 0===o&&(o={});var r=n.getInputDOMNode(),a=n.isFocused();r&&a&&(o.deferred||u(r,e,t),null!==n.selectionDeferId&&y(n.selectionDeferId),n.selectionDeferId=b((function(){n.selectionDeferId=null,u(r,e,t)})),n.previousSelection={start:e,end:t,length:Math.abs(t-e)})},n.getSelection=function(){return function(e){var t=0,n=0;if("selectionStart"in e&&"selectionEnd"in e)t=e.selectionStart,n=e.selectionEnd;else{var o=document.selection.createRange();o.parentElement()===e&&(t=-o.moveStart("character",-e.value.length),n=-o.moveEnd("character",-e.value.length))}return{start:t,end:n,length:n-t}}(n.getInputDOMNode())},n.getCursorPosition=function(){return n.getSelection().start},n.setCursorPosition=function(e){n.setSelection(e,e)},n.isFocused=function(){return n.focused},n.getBeforeMaskedValueChangeConfig=function(){var e=n.maskOptions,t=e.mask,o=e.maskChar,r=e.permanents,a=e.formatChars;return{mask:t,maskChar:o,permanents:r,alwaysShowMask:!!n.props.alwaysShowMask,formatChars:a}},n.isInputAutofilled=function(e,t,o,r){var a=n.getInputDOMNode();try{if(a.matches(":-webkit-autofill"))return!0}catch(e){}return!n.focused||r.end<o.length&&t.end===e.length},n.onChange=function(e){var t=i(i(n)).beforePasteState,o=i(i(n)).previousSelection,r=n.props.beforeMaskedValueChange,a=n.getInputValue(),s=n.value,l=n.getSelection();n.isInputAutofilled(a,l,s,o)&&(s=g(n.maskOptions,""),o={start:0,end:0,length:0}),t&&(o=t.selection,s=t.value,l={start:o.start+a.length,end:o.start+a.length,length:0},a=s.slice(0,o.start)+a+s.slice(o.end),n.beforePasteState=null);var u=function(e,t,n,o,r){var a=e.mask,s=e.prefix,i=e.lastEditablePosition,l=t,u="",c=0,p=0,d=Math.min(r.start,n.start);return n.end>r.start?p=(c=function(e,t,n,o){var r=e.mask,a=e.maskChar,s=n.split(""),i=o;return s.every((function(t){for(;s=t,f(e,n=o)&&s!==r[n];)if(++o>=r.length)return!1;var n,s;return(h(e,o,t)||t===a)&&o++,o<r.length})),o-i}(e,0,u=l.slice(r.start,n.end),d))?r.length:0:l.length<o.length&&(p=o.length-l.length),l=o,p&&(1!==p||r.length||(d=r.start===n.start?w(e,n.start):function(e,t){for(var n=t;0<=n;--n)if(!f(e,n))return n;return null}(e,n.start)),l=function(e,t,n,o){var r=n+o,a=e.maskChar,s=e.mask,i=e.prefix,l=t.split("");if(a)return l.map((function(t,o){return o<n||r<=o?t:f(e,o)?s[o]:a})).join("");for(var u=r;u<l.length;u++)f(e,u)&&(l[u]="");return n=Math.max(i.length,n),l.splice(n,r-n),t=l.join(""),g(e,t)}(e,l,d,p)),l=k(e,l,u,d),(d+=c)>=a.length?d=a.length:d<s.length&&!c?d=s.length:d>=s.length&&d<i&&c&&(d=w(e,d)),u||(u=null),{value:l=g(e,l),enteredString:u,selection:{start:d,end:d}}}(n.maskOptions,a,l,s,o),c=u.enteredString,p=u.selection,d=u.value;if(C(r)){var v=r({value:d,selection:p},{value:s,selection:o},c,n.getBeforeMaskedValueChangeConfig());d=v.value,p=v.selection}n.setInputValue(d),C(n.props.onChange)&&n.props.onChange(e),n.isWindowsPhoneBrowser?n.setSelection(p.start,p.end,{deferred:!0}):n.setSelection(p.start,p.end)},n.onFocus=function(e){var t=n.props.beforeMaskedValueChange,o=n.maskOptions,r=o.mask,a=o.prefix;if(n.focused=!0,n.mounted=!0,r){if(n.value)v(n.maskOptions,n.value)<n.maskOptions.mask.length&&n.setCursorToEnd();else{var s=g(n.maskOptions,a),i=g(n.maskOptions,s),l=v(n.maskOptions,i),u=w(n.maskOptions,l),c={start:u,end:u};if(C(t)){var p=t({value:i,selection:c},{value:n.value,selection:null},null,n.getBeforeMaskedValueChangeConfig());i=p.value,c=p.selection}var f=i!==n.getInputValue();f&&n.setInputValue(i),f&&C(n.props.onChange)&&n.props.onChange(e),n.setSelection(c.start,c.end)}n.runSaveSelectionLoop()}C(n.props.onFocus)&&n.props.onFocus(e)},n.onBlur=function(e){var t=n.props.beforeMaskedValueChange,o=n.maskOptions.mask;if(n.stopSaveSelectionLoop(),n.focused=!1,o&&!n.props.alwaysShowMask&&d(n.maskOptions,n.value)){var r="";C(t)&&(r=t({value:r,selection:null},{value:n.value,selection:n.previousSelection},null,n.getBeforeMaskedValueChangeConfig()).value);var a=r!==n.getInputValue();a&&n.setInputValue(r),a&&C(n.props.onChange)&&n.props.onChange(e)}C(n.props.onBlur)&&n.props.onBlur(e)},n.onMouseDown=function(e){!n.focused&&document.addEventListener&&(n.mouseDownX=e.clientX,n.mouseDownY=e.clientY,n.mouseDownTime=(new Date).getTime(),document.addEventListener("mouseup",(function e(t){if(document.removeEventListener("mouseup",e),n.focused){var o=Math.abs(t.clientX-n.mouseDownX),r=Math.abs(t.clientY-n.mouseDownY),a=Math.max(o,r),s=(new Date).getTime()-n.mouseDownTime;(a<=10&&s<=200||a<=5&&s<=300)&&n.setCursorToEnd()}}))),C(n.props.onMouseDown)&&n.props.onMouseDown(e)},n.onPaste=function(e){C(n.props.onPaste)&&n.props.onPaste(e),e.defaultPrevented||(n.beforePasteState={value:n.getInputValue(),selection:n.getSelection()},n.setInputValue(""))},n.handleRef=function(e){null==n.props.children&&C(n.props.inputRef)&&n.props.inputRef(e)};var o=t.mask,r=t.maskChar,s=t.formatChars,l=t.alwaysShowMask,c=t.beforeMaskedValueChange,m=t.defaultValue,O=t.value;n.maskOptions=p(o,r,s),null==m&&(m=""),null==O&&(O=m);var x=S(O);if(n.maskOptions.mask&&(l||x)&&(x=g(n.maskOptions,x),C(c))){var M=t.value;null==t.value&&(M=m),x=c({value:x,selection:null},{value:M=S(M),selection:null},null,n.getBeforeMaskedValueChangeConfig()).value}return n.value=x,n}!function(e,t){e.prototype=Object.create(t.prototype),function(e,t){for(var n=Object.getOwnPropertyNames(t),o=0;o<n.length;o++){var r=n[o],a=Object.getOwnPropertyDescriptor(t,r);a&&a.configurable&&void 0===e[r]&&Object.defineProperty(e,r,a)}}(e.prototype.constructor=e,t)}(t,e);var n=t.prototype;return n.componentDidMount=function(){this.mounted=!0,this.getInputDOMNode()&&(this.isWindowsPhoneBrowser=function(){var e=new RegExp("windows","i"),t=new RegExp("phone","i"),n=navigator.userAgent;return e.test(n)&&t.test(n)}(),this.maskOptions.mask&&this.getInputValue()!==this.value&&this.setInputValue(this.value))},n.componentDidUpdate=function(){var e=this.previousSelection,t=this.props,n=t.beforeMaskedValueChange,o=t.alwaysShowMask,r=t.mask,a=t.maskChar,s=t.formatChars,i=this.maskOptions,l=o||this.isFocused(),u=null!=this.props.value,c=u?S(this.props.value):this.value,f=e?e.start:null;if(this.maskOptions=p(r,a,s),this.maskOptions.mask){!i.mask&&this.isFocused()&&this.runSaveSelectionLoop();var h=this.maskOptions.mask&&this.maskOptions.mask!==i.mask;if(i.mask||u||(c=this.getInputValue()),(h||this.maskOptions.mask&&(c||l))&&(c=g(this.maskOptions,c)),h){var k=v(this.maskOptions,c);(null===f||k<f)&&(f=m(this.maskOptions,c)?k:w(this.maskOptions,k))}!this.maskOptions.mask||!d(this.maskOptions,c)||l||u&&this.props.value||(c="");var O={start:f,end:f};if(C(n)){var b=n({value:c,selection:O},{value:this.value,selection:this.previousSelection},null,this.getBeforeMaskedValueChangeConfig());c=b.value,O=b.selection}this.value=c;var y=this.getInputValue()!==this.value;y?(this.setInputValue(this.value),this.forceUpdate()):h&&this.forceUpdate();var x=!1;null!=O.start&&null!=O.end&&(x=!e||e.start!==O.start||e.end!==O.end),(x||y)&&this.setSelection(O.start,O.end)}else i.mask&&(this.stopSaveSelectionLoop(),this.forceUpdate())},n.componentWillUnmount=function(){this.mounted=!1,null!==this.selectionDeferId&&y(this.selectionDeferId),this.stopSaveSelectionLoop()},n.render=function(){var e,t=this.props,n=(t.mask,t.alwaysShowMask,t.maskChar,t.formatChars,t.inputRef,t.beforeMaskedValueChange,t.children),o=function(e,t){if(null==e)return{};var n,o,r={},a=Object.keys(e);for(o=0;o<a.length;o++)n=a[o],0<=t.indexOf(n)||(r[n]=e[n]);return r}(t,["mask","alwaysShowMask","maskChar","formatChars","inputRef","beforeMaskedValueChange","children"]);if(n){C(n)||l(!1);var a=["onChange","onPaste","onMouseDown","onFocus","onBlur","value","disabled","readOnly"],i=s({},o);a.forEach((function(e){return delete i[e]})),e=n(i),a.filter((function(t){return null!=e.props[t]&&e.props[t]!==o[t]})).length&&l(!1)}else e=r.createElement("input",s({ref:this.handleRef},o));var u={onFocus:this.onFocus,onBlur:this.onBlur};return this.maskOptions.mask&&(o.disabled||o.readOnly||(u.onChange=this.onChange,u.onPaste=this.onPaste,u.onMouseDown=this.onMouseDown),null!=o.value&&(u.value=this.value)),e=r.cloneElement(e,u)},t}(r.Component);e.exports=x},20:(e,t,n)=>{"use strict";var o=n(609),r=Symbol.for("react.element"),a=(Symbol.for("react.fragment"),Object.prototype.hasOwnProperty),s=o.__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED.ReactCurrentOwner,i={key:!0,ref:!0,__self:!0,__source:!0};function l(e,t,n){var o,l={},u=null,c=null;for(o in void 0!==n&&(u=""+n),void 0!==t.key&&(u=""+t.key),void 0!==t.ref&&(c=t.ref),t)a.call(t,o)&&!i.hasOwnProperty(o)&&(l[o]=t[o]);if(e&&e.defaultProps)for(o in t=e.defaultProps)void 0===l[o]&&(l[o]=t[o]);return{$$typeof:r,type:e,key:u,ref:c,props:l,_owner:s.current}}t.jsx=l,t.jsxs=l},848:(e,t,n)=>{"use strict";e.exports=n(20)},609:e=>{"use strict";e.exports=window.React},795:e=>{"use strict";e.exports=window.ReactDOM}},t={};function n(o){var r=t[o];if(void 0!==r)return r.exports;var a=t[o]={exports:{}};return e[o](a,a.exports,n),a.exports}n.n=e=>{var t=e&&e.__esModule?()=>e.default:()=>e;return n.d(t,{a:t}),t},n.d=(e,t)=>{for(var o in t)n.o(t,o)&&!n.o(e,o)&&Object.defineProperty(e,o,{enumerable:!0,get:t[o]})},n.o=(e,t)=>Object.prototype.hasOwnProperty.call(e,t),(()=>{"use strict";const e=window.wc.wcBlocksRegistry,t=window.wc.wcSettings,o=(window.wp.element,window.wp.htmlEntities);var r=n(848);const a=({})=>(0,r.jsxs)("div",{className:"wc-block-components-notice-banner is-error",role:"alert",children:[(0,r.jsx)("svg",{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 24 24",width:"24",height:"24","aria-hidden":"true",focusable:"false",children:(0,r.jsx)("path",{d:"M12 3.2c-4.8 0-8.8 3.9-8.8 8.8 0 4.8 3.9 8.8 8.8 8.8 4.8 0 8.8-3.9 8.8-8.8 0-4.8-4-8.8-8.8-8.8zm0 16c-4 0-7.2-3.3-7.2-7.2C4.8 8 8 4.8 12 4.8s7.2 3.3 7.2 7.2c0 4-3.2 7.2-7.2 7.2zM11 17h2v-6h-2v6zm0-8h2V7h-2v2z"})}),(0,r.jsx)("div",{className:"wc-block-components-notice-banner__content",children:"PagBank indisponível para pedidos inferiores a R$1,00."})]});n(609);var s=n(844),i=n.n(s);const l=({fields:e})=>(0,r.jsxs)("div",{children:[(0,r.jsx)(i(),{mask:"(99) 99999-9999",maskChar:null,children:e=>(0,r.jsx)("input",{...e,type:"text"})}),Object.keys(e).map(((t,n)=>(0,r.jsx)("div",{dangerouslySetInnerHTML:{__html:e[t]}},n)))]});var u;const c=(0,t.getSetting)("rm-pagbank-cc_data",{}),p=(0,o.decodeEntities)(c.title)||window.wp.i18n.__("Cartão de Crédito Gateway","rm-pagbank-pix"),f=e=>{const{PaymentMethodLabel:t}=e.components;return(0,r.jsx)(t,{text:p})},h=()=>c.paymentUnavailable?(0,r.jsx)("div",{className:"rm-pagbank-cc",children:(0,r.jsx)(a,{})}):(0,r.jsxs)("div",{className:"rm-pagbank-cc",children:[(0,r.jsx)(l,{fields:c.formFields}),(0,r.jsx)("input",{type:"hidden",name:"ps_connect_method",value:"cc"})]}),d={name:"rm-pagbank-cc",label:(0,r.jsx)(f,{}),content:(0,r.jsx)(h,{}),edit:(0,r.jsx)(h,{}),canMakePayment:()=>!0,ariaLabel:p,supports:{features:null!==(u=c?.supports)&&void 0!==u?u:[]}};(0,e.registerPaymentMethod)(d)})()})();