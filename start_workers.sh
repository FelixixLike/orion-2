#!/bin/sh
echo "🚀 Iniciando Modo Turbo: 8 Workers Simultáneos..."

# Esperar a que la instalación de Composer termine y Laravel funcione
echo "⏳ Esperando a que la aplicación esté lista (vendor installed)..."
until gosu www-data php artisan --version >/dev/null 2>&1; do
    echo "zzz... Esperando a que 'php artisan' responda ok..."
    sleep 5
done

echo "✅ Aplicación detectada. Lanzando 8 núcleos persistentes..."

# Función para mantener vivo un worker
run_worker() {
    worker_num=$1
    while true; do
        echo "[Worker #$worker_num] 🔄 Iniciando..."
        # Ejecutar el worker. Si termina (por error o max-time), el loop lo reinicia.
        gosu www-data php artisan queue:work --sleep=2 --tries=3 --max-time=300 --max-jobs=1000 --memory=1024 --timeout=600
        
        exit_code=$?
        echo "[Worker #$worker_num] ⚠️ Se detuvo (Código: $exit_code). Reiniciando en 3 segundos..."
        sleep 3
    done
}

# Lanzar 8 workers en segundo plano usando la función de autorrecuperación
for i in 1 2 3 4 5 6 7 8
do
    run_worker $i &
done

# Esperar indefinidamente para mantener el contenedor vivo
wait
