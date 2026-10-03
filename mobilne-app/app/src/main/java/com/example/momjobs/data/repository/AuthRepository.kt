package com.example.momjobs.data.repository

import com.example.momjobs.data.local.TokenManager
import com.example.momjobs.data.remote.AuthApi
import com.example.momjobs.data.remote.models.ApiErrorResponse
import com.example.momjobs.data.remote.models.AuthResponse
import com.example.momjobs.data.remote.models.LoginRequest
import com.example.momjobs.data.remote.models.RegisterRequest
import com.example.momjobs.data.remote.models.UserDto
import com.squareup.moshi.Moshi
import com.squareup.moshi.kotlin.reflect.KotlinJsonAdapterFactory
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import retrofit2.Response

sealed class AuthState {
    object Idle : AuthState()
    object Loading : AuthState()
    data class Authenticated(val user: UserDto) : AuthState()
    object Unauthenticated : AuthState()
    data class Error(
        val message: String,
        val validationErrors: Map<String, List<String>>? = null,
        val twoFactorRequired: Boolean = false
    ) : AuthState()
}

sealed interface AuthResult {
    data class Success(val token: String, val user: UserDto) : AuthResult
    data class Error(
        val message: String,
        val validationErrors: Map<String, List<String>>? = null,
        val twoFactorRequired: Boolean = false
    ) : AuthResult
}

open class AuthRepository(
    private val authApi: AuthApi,
    private val tokenManager: TokenManager
) {
    private val _authState = MutableStateFlow<AuthState>(AuthState.Idle)
    val authState: StateFlow<AuthState> = _authState.asStateFlow()

    private val moshi: Moshi by lazy {
        Moshi.Builder().add(KotlinJsonAdapterFactory()).build()
    }

    open suspend fun register(
        name: String,
        email: String,
        password: String,
        passwordConfirmation: String
    ): AuthResult {
        _authState.value = AuthState.Loading
        return try {
            val request = RegisterRequest(
                name = name,
                email = email,
                password = password,
                passwordConfirmation = passwordConfirmation
            )
            val response = authApi.register(request)
            handleAuthResponse(response)
        } catch (e: Exception) {
            val errorResult = AuthResult.Error("Błąd połączenia z serwerem. Sprawdź połączenie internetowe.")
            _authState.value = AuthState.Error(errorResult.message)
            errorResult
        }
    }

    open suspend fun login(
        email: String,
        password: String,
        deviceName: String,
        code: String? = null,
        recoveryCode: String? = null
    ): AuthResult {
        _authState.value = AuthState.Loading
        return try {
            val request = LoginRequest(
                email = email,
                password = password,
                deviceName = deviceName,
                code = code,
                recoveryCode = recoveryCode
            )
            val response = authApi.login(request)
            handleAuthResponse(response)
        } catch (e: Exception) {
            val errorResult = AuthResult.Error("Błąd połączenia z serwerem. Sprawdź połączenie internetowe.")
            _authState.value = AuthState.Error(errorResult.message)
            errorResult
        }
    }

    open suspend fun restoreSession(): Boolean {
        val storedToken = tokenManager.getToken()
        if (storedToken.isNullOrBlank()) {
            _authState.value = AuthState.Unauthenticated
            return false
        }

        _authState.value = AuthState.Loading
        return try {
            val response = authApi.me()
            if (response.isSuccessful) {
                val user = response.body()?.extractUser()
                if (user != null) {
                    _authState.value = AuthState.Authenticated(user)
                    true
                } else {
                    tokenManager.clearToken()
                    _authState.value = AuthState.Unauthenticated
                    false
                }
            } else {
                tokenManager.clearToken()
                _authState.value = AuthState.Unauthenticated
                false
            }
        } catch (e: Exception) {
            // Keep token if temporary offline network failure, but mark state unauthenticated or error
            _authState.value = AuthState.Unauthenticated
            false
        }
    }

    fun onUnauthorized() {
        tokenManager.clearToken()
        _authState.value = AuthState.Unauthenticated
    }

    private fun handleAuthResponse(response: Response<AuthResponse>): AuthResult {
        return if (response.isSuccessful && response.body() != null) {
            val body = response.body()!!
            tokenManager.saveToken(body.token)
            _authState.value = AuthState.Authenticated(body.user)
            AuthResult.Success(body.token, body.user)
        } else {
            val errorBody = response.errorBody()?.string()
            val parsedError = parseErrorResponse(errorBody)
            val errorMessage = parsedError?.message ?: "Nieprawidłowe dane logowania lub błąd serwera."
            val validationErrors = parsedError?.errors
            val twoFactorRequired = parsedError?.twoFactorRequired == true

            val errorResult = AuthResult.Error(
                message = errorMessage,
                validationErrors = validationErrors,
                twoFactorRequired = twoFactorRequired
            )
            _authState.value = AuthState.Error(
                message = errorMessage,
                validationErrors = validationErrors,
                twoFactorRequired = twoFactorRequired
            )
            errorResult
        }
    }

    private fun parseErrorResponse(errorJson: String?): ApiErrorResponse? {
        if (errorJson.isNullOrBlank()) return null
        return try {
            moshi.adapter(ApiErrorResponse::class.java).fromJson(errorJson)
        } catch (e: Exception) {
            null
        }
    }
}
