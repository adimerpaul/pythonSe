from fastapi import APIRouter
from app.controllers.consultas_controller import (
    get_count,
    read_consulta,
    execute_query,
    execute_getConsumoAgua,
    execute_pagarConsumo,
)

router = APIRouter()

router.get("/count", tags=["consultas"])(get_count)
router.get("/consulta", tags=["consultas"])(read_consulta)
router.post("/query", tags=["consultas"])(execute_query)
router.post("/getConsumoAgua", tags=["consultas"])(execute_getConsumoAgua)
router.post("/pagarConsumo", tags=["consultas"])(execute_pagarConsumo)