from pydantic import BaseModel

class SQLQuery(BaseModel):
    sql: str

class GetConsumoAgua(BaseModel):
    cuenta: str