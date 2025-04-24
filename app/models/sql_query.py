from pydantic import BaseModel

class SQLQuery(BaseModel):
    sql: str

class GetConsumoAgua(BaseModel):
    cuenta: str

class PagarConsumoAgua(BaseModel):
    cuenta: str
    cuenta_selasis: str
    cuotas: str
    ventanilla: str
    sucursal: str
    nfacs: str
    facturasAntiguas: str
    nroFactura: str
    cuf: str
    cufd: str
    leyenda: str
    cuis: str
    fecha: str
    glosa: str