<template>
  <div style="height:100vh;display:flex;flex-direction:column;background:#0f1c2b;color:#ffffff;font-family:'Public Sans',system-ui,sans-serif;overflow:hidden">
    <!-- Top: shop name -->
    <div style="flex:none;display:flex;align-items:center;gap:14px;padding:22px 32px;border-bottom:1px solid rgba(255,255,255,.08)">
      <div style="width:46px;height:46px;border-radius:12px;background:var(--c-accent);color:#06283a;display:grid;place-items:center;font-weight:800;font-size:20px">M</div>
      <div>
        <div style="font-size:22px;font-weight:700">Madinah Butchery</div>
        <div style="font-size:14px;color:#8fa3b6">{{ order.branch || 'Welcome' }}</div>
      </div>
      <div style="margin-left:auto;font-family:'IBM Plex Mono',monospace;font-size:26px;font-weight:600">{{ clock }}</div>
    </div>

    <!-- Paid: thank you -->
    <div v-if="order.done" style="flex:1;display:grid;place-items:center;text-align:center;padding:30px">
      <div>
        <div style="width:96px;height:96px;border-radius:50%;background:#2f9e63;display:grid;place-items:center;font-size:52px;margin:0 auto 24px">✓</div>
        <div style="font-size:44px;font-weight:800">Thank you!</div>
        <div style="font-size:22px;color:#b9c7d6;margin-top:10px">Paid {{ order.done.total }} · {{ order.done.method }}</div>
        <div v-if="order.done.change" style="margin-top:28px;background:#2f9e63;border-radius:16px;padding:18px 34px;display:inline-block">
          <div style="font-size:18px;opacity:.9">Your change</div>
          <div style="font-family:'IBM Plex Mono',monospace;font-size:52px;font-weight:700">{{ order.done.change }}</div>
        </div>
        <div style="font-size:16px;color:#8fa3b6;margin-top:26px">Receipt {{ order.done.ref }}</div>
      </div>
    </div>

    <!-- No order yet: welcome -->
    <div v-else-if="!order.lines.length" style="flex:1;display:grid;place-items:center;text-align:center">
      <div>
        <div style="font-size:48px;font-weight:800">Welcome</div>
        <div style="font-size:22px;color:#8fa3b6;margin-top:12px">Your items will show here</div>
        <div v-if="!connected" style="font-size:14px;color:#5d7285;margin-top:40px">Waiting for the POS on this computer…</div>
      </div>
    </div>

    <!-- The order: items on the left, total on the right -->
    <div v-else style="flex:1;display:flex;min-height:0">
      <div style="flex:1;min-width:0;overflow-y:auto;padding:20px 32px">
        <div v-if="order.customer && order.customer !== 'Walk in'" style="font-size:16px;color:#9cd0f5;margin-bottom:10px">Customer: {{ order.customer }}</div>
        <div v-for="(l, i) in order.lines" :key="i" style="display:flex;align-items:baseline;gap:16px;padding:14px 0;border-bottom:1px solid rgba(255,255,255,.08)">
          <div style="font-family:'IBM Plex Mono',monospace;font-size:20px;color:#9cd0f5;min-width:90px">{{ l.qty }}</div>
          <div style="flex:1;min-width:0">
            <div style="font-size:24px;font-weight:600">{{ l.name }}</div>
            <div style="font-size:15px;color:#8fa3b6;margin-top:2px">{{ l.price }}<span v-if="l.discount" style="color:#ffb3b3"> · {{ l.discount }}</span></div>
          </div>
          <div style="font-family:'IBM Plex Mono',monospace;font-size:24px;font-weight:600">{{ l.total }}</div>
        </div>
      </div>
      <div style="width:400px;flex:none;background:#08131f;padding:28px 32px;display:flex;flex-direction:column">
        <div v-for="r in order.rows" :key="r[0]" style="display:flex;font-size:19px;padding:8px 0;color:#b9c7d6">
          <div style="flex:1">{{ r[0] }}</div>
          <div style="font-family:'IBM Plex Mono',monospace">{{ r[1] }}</div>
        </div>
        <div style="margin-top:auto;border-top:2px solid rgba(255,255,255,.15);padding-top:20px">
          <div style="font-size:20px;color:#8fa3b6">Total to pay</div>
          <div style="font-family:'IBM Plex Mono',monospace;font-size:64px;font-weight:700;line-height:1.1">{{ order.total }}</div>
          <div style="font-size:15px;color:#8fa3b6;margin-top:6px">Includes GST {{ order.gst }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// Customer display: a second screen facing the customer. Open it from the POS ("Customer display" button)
// on the same computer and drag the window to the customer's monitor.
// The POS sends the order here on a BroadcastChannel named "customer-display" every time it changes.
import { onMounted, onUnmounted, ref } from 'vue';

const order = ref<any>({ lines: [], rows: [], total: '0.00', gst: '0.00', customer: '', branch: '', done: null });
const connected = ref(false);
const clock = ref('');

let channel: BroadcastChannel | null = null;
let timer: ReturnType<typeof setInterval> | undefined;

function tick() {
  const d = new Date();
  clock.value = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
}

onMounted(() => {
  tick();
  timer = setInterval(tick, 10000);
  channel = new BroadcastChannel('customer-display');
  channel.onmessage = (e) => {
    order.value = e.data;
    connected.value = true;
  };
  // Ask the POS for the current order (if it is already open).
  channel.postMessage('hello');
});

onUnmounted(() => {
  clearInterval(timer);
  if (channel) channel.close();
});
</script>
