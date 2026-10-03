package com.example.momjobs.data.remote

import com.example.momjobs.data.local.TokenManager
import okhttp3.Interceptor
import okhttp3.Response

class AuthInterceptor(
    private val tokenManager: TokenManager,
    private var onUnauthorized: (() -> Unit)? = null
) : Interceptor {

    fun setOnUnauthorizedListener(listener: () -> Unit) {
        this.onUnauthorized = listener
    }

    override fun intercept(chain: Interceptor.Chain): Response {
        val originalRequest = chain.request()
        val token = tokenManager.getToken()

        val requestBuilder = originalRequest.newBuilder()
            .header("Accept", "application/json")

        if (!token.isNullOrBlank()) {
            requestBuilder.header("Authorization", "Bearer $token")
        }

        val response = chain.proceed(requestBuilder.build())

        if (response.code == 401) {
            tokenManager.clearToken()
            onUnauthorized?.invoke()
        }

        return response
    }
}
