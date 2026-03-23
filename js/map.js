const map = L.map('map').setView([38.7223, -9.1393], 13)

let tileUrl

if(document.body.classList.contains("dark")){

	tileUrl = "https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png"

}else{

	tileUrl = "https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png"

}

L.tileLayer(tileUrl,{
	attribution:"© OpenStreetMap © CartoDB"
}).addTo(map)

const busIcon = L.icon({
	iconUrl:"https://cdn-icons-png.flaticon.com/512/61/61231.png",
	iconSize:[28,28]
})

const route = [
	[38.7223,-9.1393],
	[38.73,-9.15],
	[38.75,-9.17]
]

route.forEach(stop => {

	L.marker(stop,{
		icon:busIcon
	}).addTo(map)

})

L.polyline(route,{
	color:"#ff7a00",
	weight:6
}).addTo(map)

map.fitBounds(route)