from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from app.routes import router as api_router

app = FastAPI()

# Configurar CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Incluir las rutas
app.include_router(api_router, prefix="/api")

@app.get("/")
def read_root():
    return {"message": "API para consultar la tabla puntcons"}
# Para ejecutar la API: uvicorn main:app --reload --port=7000