package com.example.momjobs

import android.content.Context
import android.content.SharedPreferences
import com.example.momjobs.data.local.TokenManager
import com.example.momjobs.data.remote.AuthApi
import com.example.momjobs.data.remote.models.*
import com.example.momjobs.data.repository.AuthRepository
import com.example.momjobs.data.repository.AuthResult
import com.example.momjobs.data.repository.AuthState
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.*
import okhttp3.ResponseBody.Companion.toResponseBody
import org.junit.After
import org.junit.Assert.*
import org.junit.Before
import org.junit.Test
import retrofit2.Response

@OptIn(ExperimentalCoroutinesApi::class)
class AuthRepositoryTest {

    private val testDispatcher = StandardTestDispatcher()
    private lateinit var fakeAuthApi: FakeAuthApi
    private lateinit var fakeTokenManager: FakeTokenManager
    private lateinit var repository: AuthRepository

    @Before
    fun setUp() {
        Dispatchers.setMain(testDispatcher)
        fakeAuthApi = FakeAuthApi()
        fakeTokenManager = FakeTokenManager()
        repository = AuthRepository(fakeAuthApi, fakeTokenManager)
    }

    @After
    fun tearDown() {
        Dispatchers.resetMain()
    }

    @Test
    fun register_success_savesTokenAndAuthenticatesUser() = runTest {
        fakeAuthApi.registerResponse = Response.success(
            AuthResponse(
                token = "reg-token-123",
                user = UserDto(id = 10, name = "Katarzyna", email = "kasia@example.com")
            )
        )

        val result = repository.register(
            name = "Katarzyna",
            email = "kasia@example.com",
            password = "password123",
            passwordConfirmation = "password123"
        )

        assertTrue(result is AuthResult.Success)
        assertEquals("reg-token-123", fakeTokenManager.getToken())
        val authState = repository.authState.value
        assertTrue(authState is AuthState.Authenticated)
        assertEquals("Katarzyna", (authState as AuthState.Authenticated).user.name)
    }

    @Test
    fun login_success_savesTokenAndAuthenticatesUser() = runTest {
        fakeAuthApi.loginResponse = Response.success(
            AuthResponse(
                token = "login-token-456",
                user = UserDto(id = 12, name = "Anna Kowalska", email = "anna@example.com")
            )
        )

        val result = repository.login(
            email = "anna@example.com",
            password = "secretpassword",
            deviceName = "Android App"
        )

        assertTrue(result is AuthResult.Success)
        assertEquals("login-token-456", fakeTokenManager.getToken())
        val authState = repository.authState.value
        assertTrue(authState is AuthState.Authenticated)
        assertEquals("Anna Kowalska", (authState as AuthState.Authenticated).user.name)
    }

    @Test
    fun restoreSession_withValidToken_authenticatesUser() = runTest {
        fakeTokenManager.saveToken("valid-token")
        fakeAuthApi.meResponse = Response.success(
            UserMeResponse(
                data = UserDto(id = 5, name = "Marta Kowalska", email = "marta@mumjobs.test")
            )
        )

        val restored = repository.restoreSession()

        assertTrue(restored)
        val authState = repository.authState.value
        assertTrue(authState is AuthState.Authenticated)
        assertEquals("Marta Kowalska", (authState as AuthState.Authenticated).user.name)
    }

    @Test
    fun restoreSession_withInvalidToken_clearsTokenAndUnauthenticates() = runTest {
        fakeTokenManager.saveToken("invalid-token")
        fakeAuthApi.meResponse = Response.error(401, "".toResponseBody(null))

        val restored = repository.restoreSession()

        assertFalse(restored)
        assertNull(fakeTokenManager.getToken())
        assertEquals(AuthState.Unauthenticated, repository.authState.value)
    }

    @Test
    fun onUnauthorized_clearsTokenAndSetsUnauthenticatedState() = runTest {
        fakeTokenManager.saveToken("token-to-clear")

        repository.onUnauthorized()

        assertNull(fakeTokenManager.getToken())
        assertEquals(AuthState.Unauthenticated, repository.authState.value)
    }

    private class FakeTokenManager : TokenManager(DummyContext()) {
        private var token: String? = null

        override fun saveToken(token: String) {
            this.token = token
        }

        override fun getToken(): String? {
            return token
        }

        override fun clearToken() {
            token = null
        }
    }

    private class DummyContext : android.content.ContextWrapper(null) {
        override fun getApplicationContext(): Context = this
        override fun getSharedPreferences(name: String?, mode: Int): SharedPreferences {
            error("Not used in fake")
        }
    }

    private class FakeAuthApi : AuthApi {
        var registerResponse: Response<AuthResponse>? = null
        var loginResponse: Response<AuthResponse>? = null
        var meResponse: Response<UserMeResponse>? = null

        override suspend fun register(request: RegisterRequest): Response<AuthResponse> {
            return registerResponse ?: Response.error(400, "".toResponseBody(null))
        }

        override suspend fun login(request: LoginRequest): Response<AuthResponse> {
            return loginResponse ?: Response.error(400, "".toResponseBody(null))
        }

        override suspend fun me(): Response<UserMeResponse> {
            return meResponse ?: Response.error(401, "".toResponseBody(null))
        }
    }
}
