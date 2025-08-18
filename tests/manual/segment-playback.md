# Pruebas manuales para reproducción de segmentos

## Enlace válido
1. Abrir una reunión con un audio accesible, por ejemplo:
   `https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3`.
2. Hacer clic en **Reproducir fragmento** de cualquier segmento.
3. El segmento debe reproducirse y los controles deben mostrar el estado de pausa.

## Enlace inválido
1. Abrir una reunión con un enlace inexistente, por ejemplo:
   `https://example.com/archivo-inexistente.mp3`.
2. Hacer clic en **Reproducir fragmento** de un segmento.
3. Debe aparecer una alerta indicando que el audio no está listo y ningún control queda activo.
