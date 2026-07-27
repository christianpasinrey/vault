import { createApp } from 'vue';
import { createPinia } from 'pinia';

const app = createApp({ template: '<div>Vault</div>' });

app.use(createPinia());
app.mount('#app');
