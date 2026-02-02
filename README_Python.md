# Entorno Python para Juntify

## 🐍 Configuración completada

El entorno Python para el proyecto Juntify ha sido configurado correctamente con todas las dependencias necesarias.

⚠️ **IMPORTANTE**: La carpeta `python_env/` está excluida del control de versiones. Cada desarrollador debe crear su propio entorno virtual.

## 📁 Archivos creados

- `python_env/` - Entorno virtual de Python
- `requirements.txt` - Lista de dependencias instaladas
- `activate_python.bat` - Script de activación para Windows CMD
- `activate_python.ps1` - Script de activación para PowerShell

## 🚀 Cómo usar

### Opción 1: Script de activación automática (Recomendado)

**Para PowerShell:**
```powershell
.\activate_python.ps1
```

**Para CMD:**
```cmd
activate_python.bat
```

### Opción 2: Activación manual

```powershell
.\python_env\Scripts\Activate.ps1
```

## 📦 Dependencias instaladas

- **librosa** - Procesamiento de audio y análisis de características
- **numpy** - Cálculos numéricos
- **scipy** - Análisis científico y procesamiento de señales
- **PyMySQL** - Conector MySQL para Python
- **python-dotenv** - Carga de variables de entorno desde .env
- **joblib** - Paralelización de procesos
- **scikit-learn** - Machine learning
- **soundfile** - Lectura/escritura de archivos de audio
- **requests** - Peticiones HTTP

## 🛠️ Scripts disponibles

### Scripts de prueba
- `python test_identification.py` - Prueba identificación de speakers
- `python test_self_similarity.py` - Prueba similaridad de audio
- `python tools\test_audio_libs.py` - Verificación de librerías

### Herramientas de audio
- `python tools\identify_speakers.py` - Identificar speakers en archivos de audio
- `python tools\enroll_voice.py` - Enrollar nuevas voces al sistema
- `python tools\enroll_voice_simple.py` - Versión simplificada del enrollment
- `python tools\convert_to_ogg.py` - Convertir audio a formato OGG

## ⚠️ Notas importantes

1. **FFmpeg**: Ya está disponible en tu sistema (versión 8.0.1)
2. **Base de datos**: Los scripts que requieren MySQL necesitarán que la BD esté corriendo
3. **Variables de entorno**: Los scripts usan el archivo `.env` para configuración

## 🔧 Solución de problemas

### Error de conexión a MySQL
Si ves errores como "Can't connect to MySQL server", significa que necesitas:
1. Iniciar el servidor MySQL
2. Verificar las credenciales en el archivo `.env`

### Problemas de importación
Si hay errores con librerías, verifica que el entorno virtual esté activado:
```powershell
# Debería mostrar la ruta del entorno virtual
Get-Command python
```

## 📝 Agregar nuevas dependencias

Para instalar nuevas librerías:
```bash
# Con el entorno virtual activado
pip install nombre-libreria

# Actualizar requirements.txt
pip freeze > requirements.txt
```

## 🔄 Desactivar entorno

Para salir del entorno virtual:
```bash
deactivate
```