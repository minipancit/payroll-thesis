<script setup lang="ts">
import { ref, onMounted, watch } from "vue";
import L from "leaflet";
import "leaflet/dist/leaflet.css";
import Button from "./ui/button/Button.vue";

// Props (optional starting location)
const props = defineProps({
  lat: { type: Number, default: 14.5995 },
  lng: { type: Number, default: 120.9842 },
});

// Emits
const emit = defineEmits<{
  (e: "update:lat", value: number): void;
  (e: "update:lng", value: number): void;
}>();

// Map refs
const mapRef = ref<HTMLDivElement | null>(null);
const lat = ref(props.lat);
const lng = ref(props.lng);

let map: L.Map;
let marker: L.Marker;

// PH flag icon
const phFlagIcon = L.icon({
  iconUrl:
    "https://upload.wikimedia.org/wikipedia/commons/9/99/Flag_of_the_Philippines.svg",
  iconSize: [32, 24],
  iconAnchor: [16, 24],
});

onMounted(() => {
  map = L.map(mapRef.value!).setView([lat.value, lng.value], 13);

  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    attribution:
      '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
  }).addTo(map);

  marker = L.marker([lat.value, lng.value], {
    draggable: true,
    // icon: phFlagIcon,
  }).addTo(map);

  marker.on("dragend", (e) => {
    const pos = e.target.getLatLng();
    lat.value = pos.lat;
    lng.value = pos.lng;

    // emit to parent
    emit("update:lat", lat.value);
    emit("update:lng", lng.value);
  });

  map.on("click", (e: L.LeafletMouseEvent) => {
    marker.setLatLng(e.latlng);
    lat.value = e.latlng.lat;
    lng.value = e.latlng.lng;

    emit("update:lat", lat.value);
    emit("update:lng", lng.value);
  });
});

function useCurrentLocation() {
  if (!navigator.geolocation) return alert("Geolocation not supported");
  navigator.geolocation.getCurrentPosition((pos) => {
    const loc: [number, number] = [pos.coords.latitude, pos.coords.longitude];
    map.setView(loc, 13);
    marker.setLatLng(loc);
    lat.value = loc[0];
    lng.value = loc[1];

    emit("update:lat", lat.value);
    emit("update:lng", lng.value);
  });
}
</script>

<template>
  <div class="space-y-4">
    <Button variant="secondary" @click="useCurrentLocation">
      Use My Location
    </Button>

    <div
      id="map-container"
      ref="mapRef"
      class="border rounded-md"
    ></div>

    <div class="text-sm space-y-1">
      <p><strong>Latitude:</strong> {{ lat }}</p>
      <p><strong>Longitude:</strong> {{ lng }}</p>
    </div>
  </div>
</template>

<style scoped>
#map-container {
  height: 320px;
  width: 100%;
}

.leaflet-container {
  width: 100%;
  height: 100%;
}
</style>
