(function(e,t){typeof exports==`object`&&typeof module<`u`?module.exports=t(require(`vue`)):typeof define==`function`&&define.amd?define([`vue`],t):(e=typeof globalThis<`u`?globalThis:e||self,e.VueSonner=t(e.Vue))})(this,function(e){var t=Object.create,n=Object.defineProperty,r=Object.getOwnPropertyDescriptor,i=Object.getOwnPropertyNames,a=Object.getPrototypeOf,o=Object.prototype.hasOwnProperty,s=(e,t,a,s)=>{if(t&&typeof t==`object`||typeof t==`function`)for(var c=i(t),l=0,u=c.length,d;l<u;l++)d=c[l],!o.call(e,d)&&d!==a&&n(e,d,{get:(e=>t[e]).bind(null,d),enumerable:!(s=r(t,d))||s.enumerable});return e},c=(e,r,i)=>(i=e==null?{}:t(a(e)),s(r||!e||!e.__esModule?n(i,`default`,{value:e,enumerable:!0}):i,e));e=c(e);let l=1;var u=class{subscribers;toasts;dismissedToasts;constructor(){this.subscribers=[],this.toasts=[],this.dismissedToasts=new Set}subscribe=e=>(this.subscribers.push(e),()=>{let t=this.subscribers.indexOf(e);this.subscribers.splice(t,1)});publish=e=>{this.subscribers.forEach(t=>t(e))};addToast=e=>{this.publish(e),this.toasts=[...this.toasts,e]};create=e=>{let{message:t,...n}=e,r=typeof e.id==`number`||e.id&&e.id?.length>0?e.id:l++,i=this.toasts.find(e=>e.id===r),a=e.dismissible===void 0?!0:e.dismissible;return this.dismissedToasts.has(r)&&this.dismissedToasts.delete(r),i?this.toasts=this.toasts.map(n=>n.id===r?(this.publish({...n,...e,id:r,title:t}),{...n,...e,id:r,dismissible:a,title:t}):n):this.addToast({title:t,...n,dismissible:a,id:r}),r};dismiss=e=>(e?(this.dismissedToasts.add(e),requestAnimationFrame(()=>this.subscribers.forEach(t=>t({id:e,dismiss:!0})))):this.toasts.forEach(e=>{this.subscribers.forEach(t=>t({id:e.id,dismiss:!0}))}),e);message=(e,t)=>this.create({...t,message:e,type:`default`});error=(e,t)=>this.create({...t,type:`error`,message:e});success=(e,t)=>this.create({...t,type:`success`,message:e});info=(e,t)=>this.create({...t,type:`info`,message:e});warning=(e,t)=>this.create({...t,type:`warning`,message:e});loading=(e,t)=>this.create({...t,type:`loading`,message:e});promise=(t,n)=>{if(!n)return;let r;n.loading!==void 0&&(r=this.create({...n,promise:t,type:`loading`,message:n.loading,description:typeof n.description==`function`?void 0:n.description}));let i=Promise.resolve(t instanceof Function?t():t),a=r!==void 0,o,s=i.then(async t=>{o=[`resolve`,t];let i=(0,e.isVNode)(t);if(i)a=!1,this.create({id:r,type:`default`,message:t});else if(p(t)&&!t.ok){a=!1;let i=typeof n.error==`function`?await n.error(`HTTP error! status: ${t.status}`):n.error,o=typeof n.description==`function`?await n.description(`HTTP error! status: ${t.status}`):n.description,s=typeof i==`object`&&!(0,e.isVNode)(i),c=s?i:{message:i||``,id:r||``};this.create({id:r,type:`error`,description:o,...c})}else if(t instanceof Error){a=!1;let i=typeof n.error==`function`?await n.error(t):n.error,o=typeof n.description==`function`?await n.description(t):n.description,s=typeof i==`object`&&!(0,e.isVNode)(i),c=s?i:{message:i||``,id:r||``};this.create({id:r,type:`error`,description:o,...c})}else if(n.success!==void 0){a=!1;let i=typeof n.success==`function`?await n.success(t):n.success,o=typeof n.description==`function`?await n.description(t):n.description,s=typeof i==`object`&&!(0,e.isVNode)(i),c=s?i:{message:i||``,id:r||``};this.create({id:r,type:`success`,description:o,...c})}}).catch(async t=>{if(o=[`reject`,t],n.error!==void 0){a=!1;let i=typeof n.error==`function`?await n.error(t):n.error,o=typeof n.description==`function`?await n.description(t):n.description,s=typeof i==`object`&&!(0,e.isVNode)(i),c=s?i:{message:i||``,id:r||``};this.create({id:r,type:`error`,description:o,...c})}}).finally(()=>{a&&(this.dismiss(r),r=void 0),n.finally?.()}),c=()=>new Promise((e,t)=>s.then(()=>o[0]===`reject`?t(o[1]):e(o[1])).catch(t));return typeof r!=`string`&&typeof r!=`number`?{unwrap:c}:Object.assign(r,{unwrap:c})};custom=(e,t)=>{let n=t?.id||l++;return this.publish({component:e,id:n,...t}),n};getActiveToasts=()=>this.toasts.filter(e=>!this.dismissedToasts.has(e.id))};let d=new u;function f(e,t){let n=t?.id||l++;return d.create({message:e,id:n,type:`default`,...t}),n}let p=e=>e&&typeof e==`object`&&`ok`in e&&typeof e.ok==`boolean`&&`status`in e&&typeof e.status==`number`,m=f,h=()=>d.toasts,g=()=>d.getActiveToasts(),_=Object.assign(m,{success:d.success,info:d.info,warning:d.warning,error:d.error,custom:d.custom,message:d.message,promise:d.promise,dismiss:d.dismiss,loading:d.loading},{getHistory:h,getToasts:g});function v(e){return e.label!==void 0}let y=3,b=`24px`,x=`16px`,S=4e3,C=356,w=14,T=45,E=200;function D(){let t=(0,e.ref)(!1);return(0,e.watchEffect)(()=>{let e=()=>{t.value=document.hidden};return document.addEventListener(`visibilitychange`,e),()=>window.removeEventListener(`visibilitychange`,e)}),{isDocumentHidden:t}}function O(...e){return e.filter(Boolean).join(` `)}function k(e){let[t,n]=e.split(`-`),r=[];return t&&r.push(t),n&&r.push(n),r}function A(e,t){let n={};return[e,t].forEach((e,t)=>{let r=t===1,i=r?`--mobile-offset`:`--offset`,a=r?x:b;function o(e){[`top`,`right`,`bottom`,`left`].forEach(t=>{n[`${i}-${t}`]=typeof e==`number`?`${e}px`:e})}typeof e==`number`||typeof e==`string`?o(e):typeof e==`object`?[`top`,`right`,`bottom`,`left`].forEach(t=>{e[t]===void 0?n[`${i}-${t}`]=a:n[`${i}-${t}`]=typeof e[t]==`number`?`${e[t]}px`:e[t]}):o(a)}),n}let j=[`data-rich-colors`,`data-styled`,`data-mounted`,`data-promise`,`data-swiped`,`data-removed`,`data-visible`,`data-y-position`,`data-x-position`,`data-index`,`data-front`,`data-swiping`,`data-dismissible`,`data-type`,`data-invert`,`data-swipe-out`,`data-swipe-direction`,`data-expanded`],M=[`aria-label`,`data-disabled`];var N=(0,e.defineComponent)({__name:`Toast`,props:{toast:{},toasts:{},index:{},swipeDirections:{},expanded:{type:Boolean},invert:{type:Boolean},heights:{},gap:{},position:{},visibleToasts:{},expandByDefault:{type:Boolean},closeButton:{type:Boolean},interacting:{type:Boolean},style:{},cancelButtonStyle:{},actionButtonStyle:{},duration:{},class:{},unstyled:{type:Boolean},descriptionClass:{},loadingIcon:{},classes:{},icons:{},closeButtonAriaLabel:{},defaultRichColors:{type:Boolean}},emits:[`update:heights`,`removeToast`],setup(t,{emit:n}){let r=t,i=n,a=(0,e.ref)(null),o=(0,e.ref)(null),s=(0,e.ref)(!1),c=(0,e.ref)(!1),l=(0,e.ref)(!1),u=(0,e.ref)(!1),d=(0,e.ref)(!1),f=(0,e.ref)(0),p=(0,e.ref)(0),m=(0,e.ref)(r.toast.duration||r.duration||S),h=(0,e.ref)(null),g=(0,e.ref)(null),_=(0,e.computed)(()=>r.index===0),y=(0,e.computed)(()=>r.index+1<=r.visibleToasts),b=(0,e.computed)(()=>r.toast.type),x=(0,e.computed)(()=>r.toast.dismissible!==!1),C=(0,e.computed)(()=>r.toast.class||``),w=(0,e.computed)(()=>r.descriptionClass||``),A=(0,e.computed)(()=>r.heights.findIndex(e=>e.toastId===r.toast.id)||0),N=(0,e.computed)(()=>r.toast.closeButton??r.closeButton),P=(0,e.computed)(()=>r.toast.duration||r.duration||S),F=(0,e.ref)(0),I=(0,e.ref)(0),L=(0,e.ref)(null),R=(0,e.computed)(()=>r.position.split(`-`)),z=(0,e.computed)(()=>R.value[0]),B=(0,e.computed)(()=>R.value[1]),V=(0,e.computed)(()=>typeof r.toast.title!=`string`),H=(0,e.computed)(()=>typeof r.toast.description!=`string`),{isDocumentHidden:U}=D(),W=(0,e.computed)(()=>b.value&&b.value===`loading`),G=(0,e.computed)(()=>r.heights.reduce((e,t,n)=>n>=A.value?e:e+t.height,0)),K=(0,e.computed)(()=>A.value*r.gap+G.value||0);(0,e.onMounted)(()=>{m.value=P.value}),(0,e.onMounted)(()=>{if(!s.value)return;let e=g.value,t=e?.style.height;e.style.height=`auto`;let n=e.getBoundingClientRect().height;e.style.height=t,p.value=n;let a,o=r.heights.find(e=>e.toastId===r.toast.id);a=o?r.heights.map(e=>e.toastId===r.toast.id?{...e,height:n}:e):[{toastId:r.toast.id,height:n,position:r.toast.position},...r.heights],i(`update:heights`,a)});function q(){c.value=!0,f.value=K.value;let e=r.heights.filter(e=>e.toastId!==r.toast.id);i(`update:heights`,e),setTimeout(()=>{i(`removeToast`,r.toast)},E)}function J(){if(W.value||!x.value)return{};q(),r.toast.onDismiss?.(r.toast)}function Y(e){W.value||!x.value||(h.value=new Date,f.value=K.value,e.target.setPointerCapture(e.pointerId),e.target.tagName!==`BUTTON`&&(l.value=!0,L.value={x:e.clientX,y:e.clientY}))}function X(){if(u.value||!x.value)return;L.value=null;let e=Number(g.value?.style.getPropertyValue(`--swipe-amount-x`).replace(`px`,``)||0),t=Number(g.value?.style.getPropertyValue(`--swipe-amount-y`).replace(`px`,``)||0),n=new Date().getTime()-(h.value?.getTime()||0),i=a.value===`x`?e:t,s=Math.abs(i)/n;if(Math.abs(i)>=T||s>.11){f.value=K.value,r.toast.onDismiss?.(r.toast),a.value===`x`?o.value=e>0?`right`:`left`:o.value=t>0?`down`:`up`,q(),u.value=!0;return}else g.value?.style.setProperty(`--swipe-amount-x`,`0px`),g.value?.style.setProperty(`--swipe-amount-y`,`0px`);d.value=!1,l.value=!1,a.value=null}function Z(e){if(!L.value||!x.value)return;let t=window?.getSelection()?.toString()?.length??!1;if(t)return;let n=e.clientY-L.value.y,i=e.clientX-L.value.x,o=r.swipeDirections??k(r.position);!a.value&&(Math.abs(i)>1||Math.abs(n)>1)&&(a.value=Math.abs(i)>Math.abs(n)?`x`:`y`);let s={x:0,y:0},c=e=>{let t=Math.abs(e)/20;return 1/(1.5+t)};if(a.value===`y`){if(o.includes(`top`)||o.includes(`bottom`))if(o.includes(`top`)&&n<0||o.includes(`bottom`)&&n>0)s.y=n;else{let e=n*c(n);s.y=Math.abs(e)<Math.abs(n)?e:n}}else if(a.value===`x`&&(o.includes(`left`)||o.includes(`right`)))if(o.includes(`left`)&&i<0||o.includes(`right`)&&i>0)s.x=i;else{let e=i*c(i);s.x=Math.abs(e)<Math.abs(i)?e:i}(Math.abs(s.x)>0||Math.abs(s.y)>0)&&(d.value=!0),g.value?.style.setProperty(`--swipe-amount-x`,`${s.x}px`),g.value?.style.setProperty(`--swipe-amount-y`,`${s.y}px`)}(0,e.onMounted)(()=>{if(s.value=!0,!g.value)return;let e=g.value.getBoundingClientRect().height;p.value=e;let t=[{toastId:r.toast.id,height:e,position:r.toast.position},...r.heights];i(`update:heights`,t)}),(0,e.onBeforeUnmount)(()=>{if(g.value){let e=r.heights.filter(e=>e.toastId!==r.toast.id);i(`update:heights`,e)}}),(0,e.watchEffect)(e=>{if(r.toast.promise&&b.value===`loading`||r.toast.duration===1/0||r.toast.type===`loading`)return;let t,n=()=>{if(I.value<F.value){let e=new Date().getTime()-F.value;m.value-=e}I.value=new Date().getTime()},i=()=>{m.value!==1/0&&(F.value=new Date().getTime(),t=setTimeout(()=>{r.toast.onAutoClose?.(r.toast),q()},m.value))};r.expanded||r.interacting||U.value?n():i(),e(()=>{clearTimeout(t)})}),(0,e.watch)(()=>r.toast.delete,e=>{e!==void 0&&e&&q()},{deep:!0});function Q(){l.value=!1,a.value=null,L.value=null}return(t,n)=>((0,e.openBlock)(),(0,e.createElementBlock)(`li`,{tabindex:`0`,ref_key:`toastRef`,ref:g,class:(0,e.normalizeClass)((0,e.unref)(O)(r.class,C.value,t.classes?.toast,t.toast.classes?.toast,t.classes?.[b.value],t.toast?.classes?.[b.value])),"data-sonner-toast":``,"data-rich-colors":t.toast.richColors??t.defaultRichColors,"data-styled":!(t.toast.component||t.toast?.unstyled||t.unstyled),"data-mounted":s.value,"data-promise":!!t.toast.promise,"data-swiped":d.value,"data-removed":c.value,"data-visible":y.value,"data-y-position":z.value,"data-x-position":B.value,"data-index":t.index,"data-front":_.value,"data-swiping":l.value,"data-dismissible":x.value,"data-type":b.value,"data-invert":t.toast.invert||t.invert,"data-swipe-out":u.value,"data-swipe-direction":o.value,"data-expanded":!!(t.expanded||t.expandByDefault&&s.value),style:(0,e.normalizeStyle)({"--index":t.index,"--toasts-before":t.index,"--z-index":t.toasts.length-t.index,"--offset":`${c.value?f.value:K.value}px`,"--initial-height":t.expandByDefault?`auto`:`${p.value}px`,...t.style,...r.toast.style}),onDragend:Q,onPointerdown:Y,onPointerup:X,onPointermove:Z},[N.value&&!t.toast.component&&b.value!==`loading`?((0,e.openBlock)(),(0,e.createElementBlock)(`button`,{key:0,"aria-label":t.closeButtonAriaLabel||`Close toast`,"data-disabled":W.value,"data-close-button":`true`,class:(0,e.normalizeClass)((0,e.unref)(O)(t.classes?.closeButton,t.toast?.classes?.closeButton)),onClick:J},[t.icons?.close?((0,e.openBlock)(),(0,e.createBlock)((0,e.resolveDynamicComponent)(t.icons?.close),{key:0})):(0,e.renderSlot)(t.$slots,`close-icon`,{key:1})],10,M)):(0,e.createCommentVNode)(`v-if`,!0),t.toast.component?((0,e.openBlock)(),(0,e.createBlock)((0,e.resolveDynamicComponent)(t.toast.component),(0,e.mergeProps)({key:1},t.toast.componentProps,{onCloseToast:J}),null,16)):((0,e.openBlock)(),(0,e.createElementBlock)(e.Fragment,{key:2},[b.value!==`default`||t.toast.icon||t.toast.promise?((0,e.openBlock)(),(0,e.createElementBlock)(`div`,{key:0,"data-icon":``,class:(0,e.normalizeClass)((0,e.unref)(O)(t.classes?.icon,t.toast?.classes?.icon))},[t.toast.icon?((0,e.openBlock)(),(0,e.createBlock)((0,e.resolveDynamicComponent)(t.toast.icon),{key:0})):((0,e.openBlock)(),(0,e.createElementBlock)(e.Fragment,{key:1},[b.value===`loading`?(0,e.renderSlot)(t.$slots,`loading-icon`,{key:0}):b.value===`success`?(0,e.renderSlot)(t.$slots,`success-icon`,{key:1}):b.value===`error`?(0,e.renderSlot)(t.$slots,`error-icon`,{key:2}):b.value===`warning`?(0,e.renderSlot)(t.$slots,`warning-icon`,{key:3}):b.value===`info`?(0,e.renderSlot)(t.$slots,`info-icon`,{key:4}):(0,e.createCommentVNode)(`v-if`,!0)],64))],2)):(0,e.createCommentVNode)(`v-if`,!0),(0,e.createElementVNode)(`div`,{"data-content":``,class:(0,e.normalizeClass)((0,e.unref)(O)(t.classes?.content,t.toast?.classes?.content))},[(0,e.createElementVNode)(`div`,{"data-title":``,class:(0,e.normalizeClass)((0,e.unref)(O)(t.classes?.title,t.toast.classes?.title))},[V.value?((0,e.openBlock)(),(0,e.createBlock)((0,e.resolveDynamicComponent)(t.toast.title),(0,e.normalizeProps)((0,e.mergeProps)({key:0},t.toast.componentProps)),null,16)):((0,e.openBlock)(),(0,e.createElementBlock)(e.Fragment,{key:1},[(0,e.createTextVNode)((0,e.toDisplayString)(t.toast.title),1)],64))],2),t.toast.description?((0,e.openBlock)(),(0,e.createElementBlock)(`div`,{key:0,"data-description":``,class:(0,e.normalizeClass)((0,e.unref)(O)(t.descriptionClass,w.value,t.classes?.description,t.toast.classes?.description))},[H.value?((0,e.openBlock)(),(0,e.createBlock)((0,e.resolveDynamicComponent)(t.toast.description),(0,e.normalizeProps)((0,e.mergeProps)({key:0},t.toast.componentProps)),null,16)):((0,e.openBlock)(),(0,e.createElementBlock)(e.Fragment,{key:1},[(0,e.createTextVNode)((0,e.toDisplayString)(t.toast.description),1)],64))],2)):(0,e.createCommentVNode)(`v-if`,!0)],2),t.toast.cancel?((0,e.openBlock)(),(0,e.createElementBlock)(`button`,{key:1,style:(0,e.normalizeStyle)(t.toast.cancelButtonStyle||t.cancelButtonStyle),class:(0,e.normalizeClass)((0,e.unref)(O)(t.classes?.cancelButton,t.toast.classes?.cancelButton)),"data-button":``,"data-cancel":``,onClick:n[0]||=n=>{(0,e.unref)(v)(t.toast.cancel)&&x.value&&(t.toast.cancel.onClick?.(n),q())}},(0,e.toDisplayString)((0,e.unref)(v)(t.toast.cancel)?t.toast.cancel?.label:t.toast.cancel),7)):(0,e.createCommentVNode)(`v-if`,!0),t.toast.action?((0,e.openBlock)(),(0,e.createElementBlock)(`button`,{key:2,style:(0,e.normalizeStyle)(t.toast.actionButtonStyle||t.actionButtonStyle),class:(0,e.normalizeClass)((0,e.unref)(O)(t.classes?.actionButton,t.toast.classes?.actionButton)),"data-button":``,"data-action":``,onClick:n[1]||=n=>{(0,e.unref)(v)(t.toast.action)&&(t.toast.action.onClick?.(n),!n.defaultPrevented&&q())}},(0,e.toDisplayString)((0,e.unref)(v)(t.toast.action)?t.toast.action?.label:t.toast.action),7)):(0,e.createCommentVNode)(`v-if`,!0)],64))],46,j))}}),P=N,F=(e,t)=>{let n=e.__vccOpts||e;for(let[e,r]of t)n[e]=r;return n};let I={},L={xmlns:`http://www.w3.org/2000/svg`,width:`12`,height:`12`,viewBox:`0 0 24 24`,fill:`none`,stroke:`currentColor`,"stoke-width":`1.5`,"stroke-linecap":`round`,"stroke-linejoin":`round`};function R(t,n){return(0,e.openBlock)(),(0,e.createElementBlock)(`svg`,L,n[0]||=[(0,e.createElementVNode)(`line`,{x1:`18`,y1:`6`,x2:`6`,y2:`18`},null,-1),(0,e.createElementVNode)(`line`,{x1:`6`,y1:`6`,x2:`18`,y2:`18`},null,-1)])}var z=F(I,[[`render`,R]]);let B=[`data-visible`],V={class:`sonner-spinner`};var H=(0,e.defineComponent)({__name:`Loader`,props:{visible:{type:Boolean}},setup(t){let n=Array(12).fill(0);return(t,r)=>((0,e.openBlock)(),(0,e.createElementBlock)(`div`,{class:`sonner-loading-wrapper`,"data-visible":t.visible},[(0,e.createElementVNode)(`div`,V,[((0,e.openBlock)(!0),(0,e.createElementBlock)(e.Fragment,null,(0,e.renderList)((0,e.unref)(n),t=>((0,e.openBlock)(),(0,e.createElementBlock)(`div`,{key:`spinner-bar-${t}`,class:`sonner-loading-bar`}))),128))])],8,B))}}),U=H;let W={},G={xmlns:`http://www.w3.org/2000/svg`,viewBox:`0 0 20 20`,fill:`currentColor`,height:`20`,width:`20`};function K(t,n){return(0,e.openBlock)(),(0,e.createElementBlock)(`svg`,G,n[0]||=[(0,e.createElementVNode)(`path`,{"fill-rule":`evenodd`,d:`M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z`,"clip-rule":`evenodd`},null,-1)])}var q=F(W,[[`render`,K]]);let J={},Y={xmlns:`http://www.w3.org/2000/svg`,viewBox:`0 0 20 20`,fill:`currentColor`,height:`20`,width:`20`};function X(t,n){return(0,e.openBlock)(),(0,e.createElementBlock)(`svg`,Y,n[0]||=[(0,e.createElementVNode)(`path`,{"fill-rule":`evenodd`,d:`M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z`,"clip-rule":`evenodd`},null,-1)])}var Z=F(J,[[`render`,X]]);let Q={},$={xmlns:`http://www.w3.org/2000/svg`,viewBox:`0 0 24 24`,fill:`currentColor`,height:`20`,width:`20`};function ee(t,n){return(0,e.openBlock)(),(0,e.createElementBlock)(`svg`,$,n[0]||=[(0,e.createElementVNode)(`path`,{"fill-rule":`evenodd`,d:`M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003zM12 8.25a.75.75 0 01.75.75v3.75a.75.75 0 01-1.5 0V9a.75.75 0 01.75-.75zm0 8.25a.75.75 0 100-1.5.75.75 0 000 1.5z`,"clip-rule":`evenodd`},null,-1)])}var te=F(Q,[[`render`,ee]]);let ne={},re={xmlns:`http://www.w3.org/2000/svg`,viewBox:`0 0 20 20`,fill:`currentColor`,height:`20`,width:`20`};function ie(t,n){return(0,e.openBlock)(),(0,e.createElementBlock)(`svg`,re,n[0]||=[(0,e.createElementVNode)(`path`,{"fill-rule":`evenodd`,d:`M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z`,"clip-rule":`evenodd`},null,-1)])}var ae=F(ne,[[`render`,ie]]);let oe=[`aria-label`],se=[`data-sonner-theme`,`dir`,`data-theme`,`data-rich-colors`,`data-y-position`,`data-x-position`,`data-lifted`],ce=typeof window<`u`&&typeof document<`u`;function le(){if(typeof window>`u`||typeof document>`u`)return`ltr`;let e=document.documentElement.getAttribute(`dir`);return e===`auto`||!e?window.getComputedStyle(document.documentElement).direction:e}var ue=(0,e.defineComponent)({name:`Toaster`,inheritAttrs:!1,__name:`Toaster`,props:{invert:{type:Boolean,default:!1},theme:{default:`light`},position:{default:`bottom-right`},hotkey:{default:()=>[`altKey`,`KeyT`]},richColors:{type:Boolean,default:!1},expand:{type:Boolean,default:!1},duration:{},gap:{default:w},visibleToasts:{default:y},closeButton:{type:Boolean,default:!1},toastOptions:{default:()=>({})},class:{default:``},style:{},offset:{default:b},mobileOffset:{default:x},dir:{default:`auto`},swipeDirections:{},icons:{},containerAriaLabel:{default:`Notifications`}},setup(t){let n=t,r=(0,e.useAttrs)(),i=(0,e.ref)([]);function a(e,t){return i.value.filter(n=>!n.position&&t===0||n.position===e)}let o=(0,e.computed)(()=>{let e=i.value.filter(e=>e.position).map(e=>e.position);return e.length>0?Array.from(new Set([n.position].concat(e))):[n.position]}),s=(0,e.computed)(()=>{let e={};return o.value.forEach(t=>{e[t]=i.value.filter(e=>e.position===t)}),e}),c=(0,e.ref)([]),l=(0,e.ref)(!1),u=(0,e.ref)(!1),f=(0,e.ref)(n.theme===`system`?typeof window<`u`&&window.matchMedia&&window.matchMedia(`(prefers-color-scheme: dark)`).matches?`dark`:`light`:n.theme),p=(0,e.ref)(null),m=(0,e.ref)(null),h=(0,e.ref)(!1),g=n.hotkey.join(`+`).replace(/Key/g,``).replace(/Digit/g,``);function _(e){i.value.find(t=>t.id===e.id)?.delete||d.dismiss(e.id),i.value=i.value.filter(({id:t})=>t!==e.id)}function v(e){h.value&&!e.currentTarget?.contains?.(e.relatedTarget)&&(h.value=!1,m.value&&(m.value.focus({preventScroll:!0}),m.value=null))}function y(e){let t=e.target instanceof HTMLElement&&e.target.dataset.dismissible===`false`;t||h.value||(h.value=!0,m.value=e.relatedTarget)}function b(e){if(e.target){let t=e.target instanceof HTMLElement&&e.target.dataset.dismissible===`false`;if(t)return}u.value=!0}(0,e.watchEffect)(t=>{let n=d.subscribe(t=>{if(t.dismiss){requestAnimationFrame(()=>{i.value=i.value.map(e=>e.id===t.id?{...e,delete:!0}:e)});return}(0,e.nextTick)(()=>{let e=i.value.findIndex(e=>e.id===t.id);e===-1?i.value=[t,...i.value]:i.value=[...i.value.slice(0,e),{...i.value[e],...t},...i.value.slice(e+1)]})});t(n)}),(0,e.watch)(()=>n.theme,e=>{if(e!==`system`){f.value=e;return}if(e===`system`&&(window.matchMedia&&window.matchMedia(`(prefers-color-scheme: dark)`).matches?f.value=`dark`:f.value=`light`),typeof window>`u`)return;let t=window.matchMedia(`(prefers-color-scheme: dark)`);try{t.addEventListener(`change`,({matches:e})=>{e?f.value=`dark`:f.value=`light`})}catch{t.addListener(({matches:e})=>{try{e?f.value=`dark`:f.value=`light`}catch(e){console.error(e)}})}}),(0,e.watchEffect)(()=>{p.value&&m.value&&(m.value.focus({preventScroll:!0}),m.value=null,h.value=!1)}),(0,e.watchEffect)(()=>{i.value.length<=1&&(l.value=!1)}),(0,e.watchEffect)(e=>{function t(e){let t=n.hotkey.every(t=>e[t]||e.code===t),r=Array.isArray(p.value)?p.value[0]:p.value;t&&(l.value=!0,r?.focus());let i=document.activeElement===p.value||r?.contains(document.activeElement);e.code===`Escape`&&i&&(l.value=!1)}ce&&(document.addEventListener(`keydown`,t),e(()=>{document.removeEventListener(`keydown`,t)}))});function x(){l.value=!0}function S(){u.value||(l.value=!1)}function w(){l.value=!1}function T(){u.value=!1}function E(e){c.value=e}return(t,d)=>((0,e.openBlock)(),(0,e.createElementBlock)(e.Fragment,null,[(0,e.createCommentVNode)(` Remove item from normal navigation flow, only available via hotkey `),(0,e.createElementVNode)(`section`,{"aria-label":`${t.containerAriaLabel} ${(0,e.unref)(g)}`,tabIndex:-1,"aria-live":`polite`,"aria-relevant":`additions text`,"aria-atomic":`false`},[((0,e.openBlock)(!0),(0,e.createElementBlock)(e.Fragment,null,(0,e.renderList)(o.value,(o,d)=>((0,e.openBlock)(),(0,e.createElementBlock)(`ol`,(0,e.mergeProps)({key:o,ref_for:!0,ref_key:`listRef`,ref:p,"data-sonner-toaster":``,"data-sonner-theme":f.value,class:n.class,dir:t.dir===`auto`?le():t.dir,tabIndex:-1,"data-theme":t.theme,"data-rich-colors":t.richColors,"data-y-position":o.split(`-`)[0],"data-x-position":o.split(`-`)[1],"data-lifted":l.value&&i.value.length>1&&!t.expand,style:{"--front-toast-height":`${c.value[0]?.height||0}px`,"--width":`${(0,e.unref)(C)}px`,"--gap":`${t.gap}px`,...t.style,...(0,e.unref)(r).style,...(0,e.unref)(A)(t.offset,t.mobileOffset)}},t.$attrs,{onBlur:v,onFocus:y,onMouseenter:x,onMousemove:x,onMouseleave:S,onDragend:w,onPointerdown:b,onPointerup:T}),[((0,e.openBlock)(!0),(0,e.createElementBlock)(e.Fragment,null,(0,e.renderList)(a(o,d),(r,i)=>((0,e.openBlock)(),(0,e.createBlock)(P,{key:r.id,heights:c.value.filter(e=>e.position===r.position),icons:t.icons,index:i,toast:r,defaultRichColors:t.richColors,duration:t.toastOptions?.duration??t.duration,class:(0,e.normalizeClass)(t.toastOptions?.class??``),descriptionClass:t.toastOptions?.descriptionClass,invert:t.invert,visibleToasts:t.visibleToasts,closeButton:t.toastOptions?.closeButton??t.closeButton,interacting:u.value,position:o,style:(0,e.normalizeStyle)(t.toastOptions?.style),unstyled:t.toastOptions?.unstyled,classes:t.toastOptions?.classes,cancelButtonStyle:t.toastOptions?.cancelButtonStyle,actionButtonStyle:t.toastOptions?.actionButtonStyle,"close-button-aria-label":t.toastOptions?.closeButtonAriaLabel,toasts:s.value[o],expandByDefault:t.expand,gap:t.gap,expanded:l.value,swipeDirections:n.swipeDirections,"onUpdate:heights":E,onRemoveToast:_},{"close-icon":(0,e.withCtx)(()=>[(0,e.renderSlot)(t.$slots,`close-icon`,{},()=>[(0,e.createVNode)(z)])]),"loading-icon":(0,e.withCtx)(()=>[(0,e.renderSlot)(t.$slots,`loading-icon`,{},()=>[(0,e.createVNode)(U,{visible:r.type===`loading`},null,8,[`visible`])])]),"success-icon":(0,e.withCtx)(()=>[(0,e.renderSlot)(t.$slots,`success-icon`,{},()=>[(0,e.createVNode)(q)])]),"error-icon":(0,e.withCtx)(()=>[(0,e.renderSlot)(t.$slots,`error-icon`,{},()=>[(0,e.createVNode)(ae)])]),"warning-icon":(0,e.withCtx)(()=>[(0,e.renderSlot)(t.$slots,`warning-icon`,{},()=>[(0,e.createVNode)(te)])]),"info-icon":(0,e.withCtx)(()=>[(0,e.renderSlot)(t.$slots,`info-icon`,{},()=>[(0,e.createVNode)(Z)])]),_:2},1032,[`heights`,`icons`,`index`,`toast`,`defaultRichColors`,`duration`,`class`,`descriptionClass`,`invert`,`visibleToasts`,`closeButton`,`interacting`,`position`,`style`,`unstyled`,`classes`,`cancelButtonStyle`,`actionButtonStyle`,`close-button-aria-label`,`toasts`,`expandByDefault`,`gap`,`expanded`,`swipeDirections`]))),128))],16,se))),128))],8,oe)],2112))}}),de=ue;let fe={install(e){e.config.globalProperties.$toast=_,e.component(`Toaster`,de),e.provide(`toast`,_),typeof window<`u`&&(window.toast=_)}};var pe=fe;return pe});function e(e,t=`top`){if(!e||typeof document>`u`)return;let n=document.head||document.querySelector(`head`),r=n.querySelector(`:first-child`),i=document.createElement(`style`);i.appendChild(document.createTextNode(e)),t===`top`&&r?n.insertBefore(i,r):n.appendChild(i)}e(`
html[dir='ltr'],\r
[data-sonner-toaster][dir='ltr'] {\r
  --toast-icon-margin-start: -3px;\r
  --toast-icon-margin-end: 4px;\r
  --toast-svg-margin-start: -1px;\r
  --toast-svg-margin-end: 0px;\r
  --toast-button-margin-start: auto;\r
  --toast-button-margin-end: 0;\r
  --toast-close-button-start: 0;\r
  --toast-close-button-end: unset;\r
  --toast-close-button-transform: translate(-35%, -35%);
}
html[dir='rtl'],\r
[data-sonner-toaster][dir='rtl'] {\r
  --toast-icon-margin-start: 4px;\r
  --toast-icon-margin-end: -3px;\r
  --toast-svg-margin-start: 0px;\r
  --toast-svg-margin-end: -1px;\r
  --toast-button-margin-start: 0;\r
  --toast-button-margin-end: auto;\r
  --toast-close-button-start: unset;\r
  --toast-close-button-end: 0;\r
  --toast-close-button-transform: translate(35%, -35%);
}
[data-sonner-toaster] {\r
  position: fixed;\r
  width: var(--width);\r
  font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica Neue, Arial,\r
    Noto Sans, sans-serif, Apple Color Emoji, Segoe UI Emoji, Segoe UI Symbol, Noto Color Emoji;\r
  --gray1: hsl(0, 0%, 99%);\r
  --gray2: hsl(0, 0%, 97.3%);\r
  --gray3: hsl(0, 0%, 95.1%);\r
  --gray4: hsl(0, 0%, 93%);\r
  --gray5: hsl(0, 0%, 90.9%);\r
  --gray6: hsl(0, 0%, 88.7%);\r
  --gray7: hsl(0, 0%, 85.8%);\r
  --gray8: hsl(0, 0%, 78%);\r
  --gray9: hsl(0, 0%, 56.1%);\r
  --gray10: hsl(0, 0%, 52.3%);\r
  --gray11: hsl(0, 0%, 43.5%);\r
  --gray12: hsl(0, 0%, 9%);\r
  --border-radius: 8px;\r
  box-sizing: border-box;\r
  padding: 0;\r
  margin: 0;\r
  list-style: none;\r
  outline: none;\r
  z-index: 999999999;\r
  transition: transform 400ms ease;
}
[data-sonner-toaster][data-lifted='true'] {\r
  transform: translateY(-8px);
}
@media (hover: none) and (pointer: coarse) {
[data-sonner-toaster][data-lifted='true'] {\r
    transform: none;
}
}
[data-sonner-toaster][data-x-position='right'] {\r
  right: var(--offset-right);
}
[data-sonner-toaster][data-x-position='left'] {\r
  left: var(--offset-left);
}
[data-sonner-toaster][data-x-position='center'] {\r
  left: 50%;\r
  transform: translateX(-50%);
}
[data-sonner-toaster][data-y-position='top'] {\r
  top: var(--offset-top);
}
[data-sonner-toaster][data-y-position='bottom'] {\r
  bottom: var(--offset-bottom);
}
[data-sonner-toast] {\r
  --y: translateY(100%);\r
  --lift-amount: calc(var(--lift) * var(--gap));\r
  z-index: var(--z-index);\r
  position: absolute;\r
  opacity: 0;\r
  transform: var(--y);\r
  touch-action: none;\r
  transition: transform 400ms, opacity 400ms, height 400ms, box-shadow 200ms;\r
  box-sizing: border-box;\r
  outline: none;\r
  overflow-wrap: anywhere;
}
[data-sonner-toast][data-styled='true'] {\r
  padding: 16px;\r
  background: var(--normal-bg);\r
  border: 1px solid var(--normal-border);\r
  color: var(--normal-text);\r
  border-radius: var(--border-radius);\r
  box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.1);\r
  width: var(--width);\r
  font-size: 13px;\r
  display: flex;\r
  align-items: center;\r
  gap: 6px;
}
[data-sonner-toast]:focus-visible {\r
  box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.1), 0 0 0 2px rgba(0, 0, 0, 0.2);
}
[data-sonner-toast][data-y-position='top'] {\r
  top: 0;\r
  --y: translateY(-100%);\r
  --lift: 1;\r
  --lift-amount: calc(1 * var(--gap));
}
[data-sonner-toast][data-y-position='bottom'] {\r
  bottom: 0;\r
  --y: translateY(100%);\r
  --lift: -1;\r
  --lift-amount: calc(var(--lift) * var(--gap));
}
[data-sonner-toast][data-styled='true'] [data-description] {\r
  font-weight: 400;\r
  line-height: 1.4;\r
  color: #3f3f3f;
}
[data-rich-colors='true'][data-sonner-toast][data-styled='true'] [data-description] {\r
  color: inherit;
}
[data-sonner-toaster][data-sonner-theme='dark'] [data-description] {\r
  color: hsl(0, 0%, 91%);
}
[data-sonner-toast][data-styled='true'] [data-title] {\r
  font-weight: 500;\r
  line-height: 1.5;\r
  color: inherit;
}
[data-sonner-toast][data-styled='true'] [data-icon] {\r
  display: flex;\r
  height: 16px;\r
  width: 16px;\r
  position: relative;\r
  justify-content: flex-start;\r
  align-items: center;\r
  flex-shrink: 0;\r
  margin-left: var(--toast-icon-margin-start);\r
  margin-right: var(--toast-icon-margin-end);
}
[data-sonner-toast][data-promise='true'] [data-icon] > svg {\r
  opacity: 0;\r
  transform: scale(0.8);\r
  transform-origin: center;\r
  animation: sonner-fade-in 300ms ease forwards;
}
[data-sonner-toast][data-styled='true'] [data-icon] > * {\r
  flex-shrink: 0;
}
[data-sonner-toast][data-styled='true'] [data-icon] svg {\r
  margin-left: var(--toast-svg-margin-start);\r
  margin-right: var(--toast-svg-margin-end);
}
[data-sonner-toast][data-styled='true'] [data-content] {\r
  display: flex;\r
  flex-direction: column;\r
  gap: 2px;
}
[data-sonner-toast][data-styled='true'] [data-button] {\r
  border-radius: 4px;\r
  padding-left: 8px;\r
  padding-right: 8px;\r
  height: 24px;\r
  font-size: 12px;\r
  color: var(--normal-bg);\r
  background: var(--normal-text);\r
  margin-left: var(--toast-button-margin-start);\r
  margin-right: var(--toast-button-margin-end);\r
  border: none;\r
  font-weight: 500;\r
  cursor: pointer;\r
  outline: none;\r
  display: flex;\r
  align-items: center;\r
  flex-shrink: 0;\r
  transition: opacity 400ms, box-shadow 200ms;
}
[data-sonner-toast][data-styled='true'] [data-button]:focus-visible {\r
  box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.4);
}
[data-sonner-toast][data-styled='true'] [data-button]:first-of-type {\r
  margin-left: var(--toast-button-margin-start);\r
  margin-right: var(--toast-button-margin-end);
}
[data-sonner-toast][data-styled='true'] [data-cancel] {\r
  color: var(--normal-text);\r
  background: rgba(0, 0, 0, 0.08);
}
[data-sonner-toaster][data-sonner-theme='dark'] [data-sonner-toast][data-styled='true'] [data-cancel] {\r
  background: rgba(255, 255, 255, 0.3);
}
[data-sonner-toast][data-styled='true'] [data-close-button] {\r
  position: absolute;\r
  left: var(--toast-close-button-start);\r
  right: var(--toast-close-button-end);\r
  top: 0;\r
  height: 20px;\r
  width: 20px;\r
  display: flex;\r
  justify-content: center;\r
  align-items: center;\r
  padding: 0;\r
  color: var(--gray12);\r
  background: var(--normal-bg);\r
  border: 1px solid var(--gray4);\r
  transform: var(--toast-close-button-transform);\r
  border-radius: 50%;\r
  cursor: pointer;\r
  z-index: 1;\r
  transition: opacity 100ms, background 200ms, border-color 200ms;
}
[data-sonner-toast][data-styled='true'] [data-close-button]:focus-visible {\r
  box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.1), 0 0 0 2px rgba(0, 0, 0, 0.2);
}
[data-sonner-toast][data-styled='true'] [data-disabled='true'] {\r
  cursor: not-allowed;
}
[data-sonner-toast][data-styled='true']:hover [data-close-button]:hover {\r
  background: var(--gray2);\r
  border-color: var(--gray5);
}
[data-sonner-toast][data-swiping='true']::before {\r
  content: '';\r
  position: absolute;\r
  left: -100%;\r
  right: -100%;\r
  height: 100%;\r
  z-index: -1;
}
[data-sonner-toast][data-y-position='top'][data-swiping='true']::before {\r
  bottom: 50%;\r
  transform: scaleY(3) translateY(50%);
}
[data-sonner-toast][data-y-position='bottom'][data-swiping='true']::before {\r
  top: 50%;\r
  transform: scaleY(3) translateY(-50%);
}
[data-sonner-toast][data-swiping='false'][data-removed='true']::before {\r
  content: '';\r
  position: absolute;\r
  inset: 0;\r
  transform: scaleY(2);
}
[data-sonner-toast][data-expanded='true']::after {\r
  content: '';\r
  position: absolute;\r
  left: 0;\r
  height: calc(var(--gap) + 1px);\r
  bottom: 100%;\r
  width: 100%;
}
[data-sonner-toast][data-mounted='true'] {\r
  --y: translateY(0);\r
  opacity: 1;
}
[data-sonner-toast][data-expanded='false'][data-front='false'] {\r
  --scale: var(--toasts-before) * 0.05 + 1;\r
  --y: translateY(calc(var(--lift-amount) * var(--toasts-before))) scale(calc(-1 * var(--scale)));\r
  height: var(--front-toast-height);
}
[data-sonner-toast] > * {\r
  transition: opacity 400ms;
}
[data-sonner-toast][data-x-position='right'] {\r
  right: 0;
}
[data-sonner-toast][data-x-position='left'] {\r
  left: 0;
}
[data-sonner-toast][data-expanded='false'][data-front='false'][data-styled='true'] > * {\r
  opacity: 0;
}
[data-sonner-toast][data-visible='false'] {\r
  opacity: 0;\r
  pointer-events: none;
}
[data-sonner-toast][data-mounted='true'][data-expanded='true'] {\r
  --y: translateY(calc(var(--lift) * var(--offset)));\r
  height: var(--initial-height);
}
[data-sonner-toast][data-removed='true'][data-front='true'][data-swipe-out='false'] {\r
  --y: translateY(calc(var(--lift) * -100%));\r
  opacity: 0;
}
[data-sonner-toast][data-removed='true'][data-front='false'][data-swipe-out='false'][data-expanded='true'] {\r
  --y: translateY(calc(var(--lift) * var(--offset) + var(--lift) * -100%));\r
  opacity: 0;
}
[data-sonner-toast][data-removed='true'][data-front='false'][data-swipe-out='false'][data-expanded='false'] {\r
  --y: translateY(40%);\r
  opacity: 0;\r
  transition: transform 500ms, opacity 200ms;
}
[data-sonner-toast][data-removed='true'][data-front='false']::before {\r
  height: calc(var(--initial-height) + 20%);
}
[data-sonner-toast][data-swiping='true'] {\r
  transform: var(--y) translateY(var(--swipe-amount-y, 0px)) translateX(var(--swipe-amount-x, 0px));\r
  transition: none;
}
[data-sonner-toast][data-swiped='true'] {\r
  user-select: none;
}
[data-sonner-toast][data-swipe-out='true'][data-y-position='bottom'],\r
[data-sonner-toast][data-swipe-out='true'][data-y-position='top'] {\r
  animation-duration: 200ms;\r
  animation-timing-function: ease-out;\r
  animation-fill-mode: forwards;
}
[data-sonner-toast][data-swipe-out='true'][data-swipe-direction='left'] {\r
  animation-name: swipe-out-left;
}
[data-sonner-toast][data-swipe-out='true'][data-swipe-direction='right'] {\r
  animation-name: swipe-out-right;
}
[data-sonner-toast][data-swipe-out='true'][data-swipe-direction='up'] {\r
  animation-name: swipe-out-up;
}
[data-sonner-toast][data-swipe-out='true'][data-swipe-direction='down'] {\r
  animation-name: swipe-out-down;
}
@keyframes swipe-out-left {
from {\r
    transform: var(--y) translateX(var(--swipe-amount-x));\r
    opacity: 1;
}
to {\r
    transform: var(--y) translateX(calc(var(--swipe-amount-x) - 100%));\r
    opacity: 0;
}
}
@keyframes swipe-out-right {
from {\r
    transform: var(--y) translateX(var(--swipe-amount-x));\r
    opacity: 1;
}
to {\r
    transform: var(--y) translateX(calc(var(--swipe-amount-x) + 100%));\r
    opacity: 0;
}
}
@keyframes swipe-out-up {
from {\r
    transform: var(--y) translateY(var(--swipe-amount-y));\r
    opacity: 1;
}
to {\r
    transform: var(--y) translateY(calc(var(--swipe-amount-y) - 100%));\r
    opacity: 0;
}
}
@keyframes swipe-out-down {
from {\r
    transform: var(--y) translateY(var(--swipe-amount-y));\r
    opacity: 1;
}
to {\r
    transform: var(--y) translateY(calc(var(--swipe-amount-y) + 100%));\r
    opacity: 0;
}
}
@media (max-width: 600px) {
[data-sonner-toaster] {\r
    position: fixed;\r
    right: var(--mobile-offset-right);\r
    left: var(--mobile-offset-left);\r
    width: 100%;
}
[data-sonner-toaster][dir='rtl'] {\r
    left: calc(var(--mobile-offset-left) * -1);
}
[data-sonner-toaster] [data-sonner-toast] {\r
    left: 0;\r
    right: 0;\r
    width: calc(100% - var(--mobile-offset-left) * 2);
}
[data-sonner-toaster][data-x-position='left'] {\r
    left: var(--mobile-offset-left);
}
[data-sonner-toaster][data-y-position='bottom'] {\r
    bottom: var(--mobile-offset-bottom);
}
[data-sonner-toaster][data-y-position='top'] {\r
    top: var(--mobile-offset-top);
}
[data-sonner-toaster][data-x-position='center'] {\r
    left: var(--mobile-offset-left);\r
    right: var(--mobile-offset-right);\r
    transform: none;
}
}
[data-sonner-toaster][data-sonner-theme='light'] {\r
  --normal-bg: #fff;\r
  --normal-border: var(--gray4);\r
  --normal-text: var(--gray12);\r
\r
  --success-bg: hsl(143, 85%, 96%);\r
  --success-border: hsl(145, 92%, 87%);\r
  --success-text: hsl(140, 100%, 27%);\r
\r
  --info-bg: hsl(208, 100%, 97%);\r
  --info-border: hsl(221, 91%, 93%);\r
  --info-text: hsl(210, 92%, 45%);\r
\r
  --warning-bg: hsl(49, 100%, 97%);\r
  --warning-border: hsl(49, 91%, 84%);\r
  --warning-text: hsl(31, 92%, 45%);\r
\r
  --error-bg: hsl(359, 100%, 97%);\r
  --error-border: hsl(359, 100%, 94%);\r
  --error-text: hsl(360, 100%, 45%);
}
[data-sonner-toaster][data-sonner-theme='light'] [data-sonner-toast][data-invert='true'] {\r
  --normal-bg: #000;\r
  --normal-border: hsl(0, 0%, 20%);\r
  --normal-text: var(--gray1);
}
[data-sonner-toaster][data-sonner-theme='dark'] [data-sonner-toast][data-invert='true'] {\r
  --normal-bg: #fff;\r
  --normal-border: var(--gray3);\r
  --normal-text: var(--gray12);
}
[data-sonner-toaster][data-sonner-theme='dark'] {\r
  --normal-bg: #000;\r
  --normal-bg-hover: hsl(0, 0%, 12%);\r
  --normal-border: hsl(0, 0%, 20%);\r
  --normal-border-hover: hsl(0, 0%, 25%);\r
  --normal-text: var(--gray1);\r
\r
  --success-bg: hsl(150, 100%, 6%);\r
  --success-border: hsl(147, 100%, 12%);\r
  --success-text: hsl(150, 86%, 65%);\r
\r
  --info-bg: hsl(215, 100%, 6%);\r
  --info-border: hsl(223, 43%, 17%);\r
  --info-text: hsl(216, 87%, 65%);\r
\r
  --warning-bg: hsl(64, 100%, 6%);\r
  --warning-border: hsl(60, 100%, 9%);\r
  --warning-text: hsl(46, 87%, 65%);\r
\r
  --error-bg: hsl(358, 76%, 10%);\r
  --error-border: hsl(357, 89%, 16%);\r
  --error-text: hsl(358, 100%, 81%);
}
[data-sonner-toaster][data-sonner-theme='dark'] [data-sonner-toast] [data-close-button] {\r
  background: var(--normal-bg);\r
  border-color: var(--normal-border);\r
  color: var(--normal-text);
}
[data-sonner-toaster][data-sonner-theme='dark'] [data-sonner-toast] [data-close-button]:hover {\r
  background: var(--normal-bg-hover);\r
  border-color: var(--normal-border-hover);
}
[data-rich-colors='true'][data-sonner-toast][data-type='success'] {\r
  background: var(--success-bg);\r
  border-color: var(--success-border);\r
  color: var(--success-text);
}
[data-rich-colors='true'][data-sonner-toast][data-type='success'] [data-close-button] {\r
  background: var(--success-bg);\r
  border-color: var(--success-border);\r
  color: var(--success-text);
}
[data-rich-colors='true'][data-sonner-toast][data-type='info'] {\r
  background: var(--info-bg);\r
  border-color: var(--info-border);\r
  color: var(--info-text);
}
[data-rich-colors='true'][data-sonner-toast][data-type='info'] [data-close-button] {\r
  background: var(--info-bg);\r
  border-color: var(--info-border);\r
  color: var(--info-text);
}
[data-rich-colors='true'][data-sonner-toast][data-type='warning'] {\r
  background: var(--warning-bg);\r
  border-color: var(--warning-border);\r
  color: var(--warning-text);
}
[data-rich-colors='true'][data-sonner-toast][data-type='warning'] [data-close-button] {\r
  background: var(--warning-bg);\r
  border-color: var(--warning-border);\r
  color: var(--warning-text);
}
[data-rich-colors='true'][data-sonner-toast][data-type='error'] {\r
  background: var(--error-bg);\r
  border-color: var(--error-border);\r
  color: var(--error-text);
}
[data-rich-colors='true'][data-sonner-toast][data-type='error'] [data-close-button] {\r
  background: var(--error-bg);\r
  border-color: var(--error-border);\r
  color: var(--error-text);
}
.sonner-loading-wrapper {\r
  --size: 16px;\r
  height: var(--size);\r
  width: var(--size);\r
  position: absolute;\r
  inset: 0;\r
  z-index: 10;
}
.sonner-loading-wrapper[data-visible='false'] {\r
  transform-origin: center;\r
  animation: sonner-fade-out 0.2s ease forwards;
}
.sonner-spinner {\r
  position: relative;\r
  top: 50%;\r
  left: 50%;\r
  height: var(--size);\r
  width: var(--size);
}
.sonner-loading-bar {\r
  animation: sonner-spin 1.2s linear infinite;\r
  background: var(--gray11);\r
  border-radius: 6px;\r
  height: 8%;\r
  left: -10%;\r
  position: absolute;\r
  top: -3.9%;\r
  width: 24%;
}
.sonner-loading-bar:nth-child(1) {\r
  animation-delay: -1.2s;\r
  transform: rotate(0.0001deg) translate(146%);
}
.sonner-loading-bar:nth-child(2) {\r
  animation-delay: -1.1s;\r
  transform: rotate(30deg) translate(146%);
}
.sonner-loading-bar:nth-child(3) {\r
  animation-delay: -1s;\r
  transform: rotate(60deg) translate(146%);
}
.sonner-loading-bar:nth-child(4) {\r
  animation-delay: -0.9s;\r
  transform: rotate(90deg) translate(146%);
}
.sonner-loading-bar:nth-child(5) {\r
  animation-delay: -0.8s;\r
  transform: rotate(120deg) translate(146%);
}
.sonner-loading-bar:nth-child(6) {\r
  animation-delay: -0.7s;\r
  transform: rotate(150deg) translate(146%);
}
.sonner-loading-bar:nth-child(7) {\r
  animation-delay: -0.6s;\r
  transform: rotate(180deg) translate(146%);
}
.sonner-loading-bar:nth-child(8) {\r
  animation-delay: -0.5s;\r
  transform: rotate(210deg) translate(146%);
}
.sonner-loading-bar:nth-child(9) {\r
  animation-delay: -0.4s;\r
  transform: rotate(240deg) translate(146%);
}
.sonner-loading-bar:nth-child(10) {\r
  animation-delay: -0.3s;\r
  transform: rotate(270deg) translate(146%);
}
.sonner-loading-bar:nth-child(11) {\r
  animation-delay: -0.2s;\r
  transform: rotate(300deg) translate(146%);
}
.sonner-loading-bar:nth-child(12) {\r
  animation-delay: -0.1s;\r
  transform: rotate(330deg) translate(146%);
}
@keyframes sonner-fade-in {
0% {\r
    opacity: 0;\r
    transform: scale(0.8);
}
100% {\r
    opacity: 1;\r
    transform: scale(1);
}
}
@keyframes sonner-fade-out {
0% {\r
    opacity: 1;\r
    transform: scale(1);
}
100% {\r
    opacity: 0;\r
    transform: scale(0.8);
}
}
@keyframes sonner-spin {
0% {\r
    opacity: 1;
}
100% {\r
    opacity: 0.15;
}
}
@media (prefers-reduced-motion) {
[data-sonner-toast],\r
  [data-sonner-toast] > *,\r
  .sonner-loading-bar {\r
    transition: none !important;\r
    animation: none !important;
}
}
.sonner-loader {\r
  position: absolute;\r
  top: 50%;\r
  left: 50%;\r
  transform: translate(-50%, -50%);\r
  transform-origin: center;\r
  transition: opacity 200ms, transform 200ms;
}
.sonner-loader[data-visible='false'] {\r
  opacity: 0;\r
  transform: scale(0.8) translate(-50%, -50%);
}\r
`,`top`);