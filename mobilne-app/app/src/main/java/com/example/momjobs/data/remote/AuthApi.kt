package com.example.momjobs.data.remote

import com.example.momjobs.data.remote.models.AuthResponse
import com.example.momjobs.data.remote.models.LoginRequest
import com.example.momjobs.data.remote.models.RegisterRequest
import com.example.momjobs.data.remote.models.UserMeResponse
import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST

interface AuthApi {

    @POST("auth/register")
    suspend fun register(@Body request: RegisterRequest): Response<AuthResponse>

    @POST("auth/login")
    suspend fun login(@Body request: LoginRequest): Response<AuthResponse>

    @GET("auth/me")
    suspend fun me(): Response<UserMeResponse>
}
