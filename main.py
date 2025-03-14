from fastapi import FastAPI
from pydantic import BaseModel
from fastapi.middleware.cors import CORSMiddleware
import pyodbc
import asyncio
import asyncpg

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
async def read_consulta():
    try:
        connPg = await asyncpg.connect(
                    user="seladeveloper",
                    password="rootsela",
                    database="seladb",
                    host="192.168.1.31",
                    port=5432
                )
        connDbf = pyodbc.connect(f'DSN={dns};UID={usuario};PWD={password}')
        cursor = connDbf.cursor()

        cursor.execute("SELECT fic_nros,fic_flag FROM ficha where fic_nros like 'N25%' and fic_flag=.T.")
        columns = [column[0] for column in cursor.description]
        result = [
            {columns[i]: str(row[i]).strip() if row[i] is not None else "" for i in range(len(columns))}
            for row in cursor.fetchall()
        ]
        ficNros=[row["fic_nros"] for row in result]
        placeholders = ', '.join(f"${i+1}" for i in range(len(ficNros)))
        query = f"SELECT fic_nros,fic_flag FROM hidrometros.ficha WHERE fic_nros IN ({placeholders})"
#         query = f"UPDATE hidrometros.ficha SET fic_flag=true WHERExxx fic_nros IN ({placeholders})"
        fichas = await connPg.fetch(query, *ficNros)
        return fichas
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
