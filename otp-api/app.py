from fastapi import FastAPI, HTTPException

from pydantic import BaseModel, Field

import redis

import secrets

import time



app = FastAPI(title="OTP Transaccional API", version="1.0")



r = redis.Redis(host="127.0.0.1", port=6379, db=0, decode_responses=True)



OTP_TTL_SECONDS = 90

MAX_ATTEMPTS = 3

BLOCK_SECONDS = 600  # 10 min



class OTPRequest(BaseModel):

    user_id: str = Field(min_length=1, max_length=64)

    operation_id: str = Field(min_length=1, max_length=64)



class OTPValidate(BaseModel):

    user_id: str = Field(min_length=1, max_length=64)

    operation_id: str = Field(min_length=1, max_length=64)

    otp: str = Field(min_length=6, max_length=6)



def k_block(user_id: str) -> str:

    return f"otp:block:{user_id}"



def k_attempts(user_id: str, operation_id: str) -> str:

    return f"otp:attempts:{user_id}:{operation_id}"



def k_code(user_id: str, operation_id: str) -> str:

    return f"otp:code:{user_id}:{operation_id}"



@app.get("/health")

def health():

    return {"status": "ok", "ts": int(time.time())}

@app.post("/otp/request")

def otp_request(payload: OTPRequest):

    # bloqueo por usuario

    if r.exists(k_block(payload.user_id)):

        raise HTTPException(status_code=429, detail="Usuario bloqueado temporalmente por intentos fallidos")

    # geneerar OTP 6 d[igitos
    otp = f"{secrets.randbelow(1000000):06d}"

    # guardar OTP con TTL
    r.set(k_code(payload.user_id, payload.operation_id), otp, ex=OTP_TTL_SECONDS)


    # reset intentos para esa operacion
    r.delete(k_attempts(payload.user_id, payload.operation_id))

    # Para laboratorio: devolver OTP en respuesta.
    # En un entorno real se enviaria por SMS/Email/Push y NO se retornaria.

    return {
        "user_id": payload.user_id, 
        "operation_id": payload.operation_id, 
        "otp": otp, 
        "ttl_seconds": OTP_TTL_SECONDS
    }

@app.post("/otp/validate")

def otp_validate(payload: OTPValidate):

    # bloqueo por usuario

    if r.exists(k_block(payload.user_id)):

        raise HTTPException(status_code=429, detail="Usuario bloqueado temporalmente por intentos fallidos")



    key_code = k_code(payload.user_id, payload.operation_id)

    expected = r.get(key_code)



    if expected is None:

        raise HTTPException(status_code=400, detail="OTP inexistente o expirado")



    if payload.otp != expected:

        # incrementar intentos

        akey = k_attempts(payload.user_id, payload.operation_id)

        attempts = r.incr(akey)

        # poner TTL a intentos si no lo tiene

        if r.ttl(akey) < 0:

            r.expire(akey, OTP_TTL_SECONDS)



        if attempts >= MAX_ATTEMPTS:

            # bloquear usuario y limpiar OTP

            r.set(k_block(payload.user_id), "1", ex=BLOCK_SECONDS)

            r.delete(key_code)

            r.delete(akey)

            raise HTTPException(status_code=429, detail="Demasiados intentos. Usuario bloqueado temporalmente")



        raise HTTPException(status_code=401, detail=f"OTP invalido. Intento {attempts}/{MAX_ATTEMPTS}")



    # OTP correcto: anti-replay (se elimina inmediatamente)

    r.delete(key_code)

    r.delete(k_attempts(payload.user_id, payload.operation_id))



    return {"valid": True, "user_id": payload.user_id, "operation_id": payload.operation_id}
