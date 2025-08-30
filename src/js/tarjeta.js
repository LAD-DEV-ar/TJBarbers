function actualizarTarjetaUI(visitas, total) {
  const contenedor = document.getElementById("casillas");
  contenedor.innerHTML = ""; // limpiar antes de renderizar

  for (let i = 1; i <= total; i++) {
    const casilla = document.createElement("div");
    casilla.classList.add("casilla");

    if (i <= visitas) {
      casilla.classList.add("activa");
      casilla.innerHTML = "✓"; // tilde de check
    } else {
      casilla.innerHTML = i; // opcional: mostrar el número de paso
    }

    contenedor.appendChild(casilla);
  }
}

// Ejemplo: cliente con 6 visitas de 10
actualizarTarjetaUI(0, 20);
