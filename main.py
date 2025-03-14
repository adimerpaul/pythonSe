from fastapi import FastAPI
from pydantic import BaseModel
from fastapi.middleware.cors import CORSMiddleware
import pyodbc

dns = 'selaFiles'
usuario = ''
password = ''

# Configuración de la API
app = FastAPI()

# Configurar CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # Puedes especificar dominios en lugar de "*" por seguridad
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Función para obtener el número de registros en la tabla puntcons
def get_count():
    try:
        conn = pyodbc.connect(f'DSN={dns};UID={usuario};PWD={password}')
        cursor = conn.cursor()
        cursor.execute("SELECT count(*) FROM puntcons")
        result = cursor.fetchone()
        cursor.close()
        conn.close()
        return {"count": result[0]}
    except Exception as e:
        return {"error": str(e)}

@app.get("/count")
def read_count():
    return get_count()

@app.get("/")
def read_root():
    return {"message": "API para consultar la tabla puntcons"}

@app.get("/consulta")
def read_consulta():
    try:
        conn = pyodbc.connect(f'DSN={dns};UID={usuario};PWD={password}')
        cursor = conn.cursor()
        cursor.execute("SELECT * FROM puntcons")

        # Obtener nombres de columnas
        columns = [column[0] for column in cursor.description]

        # Convertir datos en lista de diccionarios, aplicando `strip` para quitar espacios
        result = [
            {columns[i]: str(row[i]).strip() if row[i] is not None else "" for i in range(len(columns))}
            for row in cursor.fetchall()
        ]

        cursor.close()
        conn.close()

        return {"data": result}
    except Exception as e:
        return {"error": str(e)}

class SQLQuery(BaseModel):
    sql: str

@app.post("/query")
def read_query(sql_query: SQLQuery):
    try:
        conn = pyodbc.connect(f'DSN={dns};UID={usuario};PWD={password}')
        cursor = conn.cursor()
        cursor.execute(sql_query.sql)

        # Obtener nombres de columnas
        columns = [column[0] for column in cursor.description]

        # Convertir datos en lista de diccionarios, aplicando `strip` para quitar espacios
        result = [
            {columns[i]: str(row[i]).strip() if row[i] is not None else "" for i in range(len(columns))}
            for row in cursor.fetchall()
        ]

        cursor.close()
        conn.close()

        return {"data": result}
    except Exception as e:
        return {"error": str(e)}

# Para ejecutar la API: uvicorn main:app --reload --port=7000
