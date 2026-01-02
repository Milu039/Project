async function loadWeather() {
    const dashboard = document.getElementById("dashboard");
    const areaType = dashboard.dataset.areaType || "0";
    const areaId = dashboard.dataset.areaId || 0;

    const res = await fetch(`weather_api.php?area_type=${areaType}&area_id=${areaId}`);
    const data = await res.json();

    const codeIcons = {0:"☀️",1:"🌤️",2:"⛅",3:"☁️",61:"🌧️",95:"⛈️"};
    const codeDescriptions = {0:"Clear",1:"Mainly Clear",2:"Partly Cloudy",3:"Cloudy",61:"Rain",95:"Thunderstorm"};

    const iconDiv = document.getElementById("weatherIcon");
    if(iconDiv) iconDiv.innerText = codeIcons[data.code] || "❓";

    const tempDiv = document.getElementById("weatherTemp");
    if(tempDiv) tempDiv.innerText = data.temp + "°C";

    const descDiv = document.getElementById("weatherDesc");
    if(descDiv) descDiv.innerText = codeDescriptions[data.code] || "Unknown";

    if(data.code == 95){
        alert("⚠️ Thunderstorm Warning! Please stay indoors and avoid going out.");
    }
}

loadWeather();
