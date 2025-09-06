# Pruebas manuales para remultiplexado de grabaciones

1. Iniciar una nueva grabación desde la interfaz de reunión.
2. Observar en las herramientas de desarrollador que cada segmento genera una petición `POST /api/recordings/chunk`.
3. Detener la grabación y verificar que se realiza `POST /api/recordings/concat` y que la respuesta es un archivo WebM válido.
4. Confirmar que la transcripción se inicia sobre el archivo remultiplexado.
