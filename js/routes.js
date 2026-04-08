const routes = [

	{
		time:"50 min",
		description:"🚌 728 → 🚌 4708"
	},

	{
		time:"1h 20 min",
		description:"🚌 2768 → 🚌 2736"
	}

]

const container = document.getElementById("routes")

routes.forEach(route => {

	const card = document.createElement("div")

	card.className = "route-card"

	card.innerHTML = `
		<strong>${route.time}</strong>
		<br>
		${route.description}
	`

	card.onclick = drawRoute

	container.appendChild(card)

})


function drawRoute(){

	const coords = [

		[38.7223,-9.1393],
		[38.735,-9.15],
		[38.75,-9.17]

	]

	L.polyline(coords,{
		color:"orange",
		weight:6
	}).addTo(map)

	map.fitBounds(coords)

}