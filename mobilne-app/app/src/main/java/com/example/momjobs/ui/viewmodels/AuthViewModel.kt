package com.example.momjobs.ui.viewmodels

import android.os.Build
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import com.example.momjobs.data.remote.models.UserDto
import com.example.momjobs.data.repository.AuthRepository
import com.example.momjobs.data.repository.AuthResult
import com.example.momjobs.data.repository.AuthState
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.flow.stateIn
import kotlinx.coroutines.launch

class AuthViewModel(private val authRepository: AuthRepository) : ViewModel() {

    val authState: StateFlow<AuthState> = authRepository.authState

    val currentUser: StateFlow<UserDto?> = authState
        .map { (it as? AuthState.Authenticated)?.user }
        .stateIn(viewModelScope, SharingStarted.WhileSubscribed(5000), null)

    val defaultDeviceName: String
        get() = "${Build.MANUFACTURER.replaceFirstChar { it.uppercase() }} ${Build.MODEL}"

    init {
        restoreSession()
    }

    fun restoreSession() {
        viewModelScope.launch {
            authRepository.restoreSession()
        }
    }

    fun login(
        email: String,
        password: String,
        deviceName: String = defaultDeviceName,
        code: String? = null,
        recoveryCode: String? = null,
        onSuccess: () -> Unit = {},
        onError: (String) -> Unit = {}
    ) {
        viewModelScope.launch {
            val result = authRepository.login(
                email = email.trim(),
                password = password,
                deviceName = deviceName.ifBlank { defaultDeviceName },
                code = code?.trim()?.ifBlank { null },
                recoveryCode = recoveryCode?.trim()?.ifBlank { null }
            )
            when (result) {
                is AuthResult.Success -> onSuccess()
                is AuthResult.Error -> onError(result.message)
            }
        }
    }

    fun register(
        name: String,
        email: String,
        password: String,
        passwordConfirmation: String,
        onSuccess: () -> Unit = {},
        onError: (String) -> Unit = {}
    ) {
        viewModelScope.launch {
            val result = authRepository.register(
                name = name.trim(),
                email = email.trim(),
                password = password,
                passwordConfirmation = passwordConfirmation
            )
            when (result) {
                is AuthResult.Success -> onSuccess()
                is AuthResult.Error -> onError(result.message)
            }
        }
    }
}

class AuthViewModelFactory(private val authRepository: AuthRepository) : ViewModelProvider.Factory {
    override fun <T : ViewModel> create(modelClass: Class<T>): T {
        if (modelClass.isAssignableFrom(AuthViewModel::class.java)) {
            @Suppress("UNCHECKED_CAST")
            return AuthViewModel(authRepository) as T
        }
        throw IllegalArgumentException("Unknown ViewModel class")
    }
}
