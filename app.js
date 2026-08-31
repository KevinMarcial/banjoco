/**
 * Valida los datos del formulario administrativo antes de enviarlos a PHP
 */
function validarFormulario() {
    const nombre = document.getElementById("nombre").value.trim();
    const apellidos = document.getElementById("apellidos").value.trim();
    const num_cuenta = document.getElementById("num_cuenta").value.trim();
    const saldo = parseFloat(document.getElementById("saldo").value);

    if (nombre === "" || apellidos === "") {
        alert("⚠️ Validación: El nombre y apellidos son obligatorios.");
        return false;
    }

    // CORREGIDO: Se definió el valor límite (5 caracteres mínimos para una cuenta estándar)
    if (num_cuenta.length < 5) {
        alert("⚠️ Validación: El número de cuenta es demasiado corto (mínimo 5 caracteres).");
        return false;
    }

    if (isNaN(saldo) || saldo < 0) {
        alert("⚠️ Validación: El saldo inicial no puede ser negativo.");
        return false;
    }

    return true; 
}