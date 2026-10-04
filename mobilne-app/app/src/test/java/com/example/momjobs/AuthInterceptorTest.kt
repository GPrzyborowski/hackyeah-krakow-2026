package com.example.momjobs

import com.example.momjobs.data.local.TokenManager
import com.example.momjobs.data.remote.AuthInterceptor
import okhttp3.*
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.ResponseBody.Companion.toResponseBody
import org.junit.Assert.*
import org.junit.Test
import java.util.concurrent.TimeUnit

class AuthInterceptorTest {

    @Test
    fun interceptor_addsAuthorizationHeader_whenTokenIsPresent() {
        val tokenManager = FakeTokenManager()
        tokenManager.saveToken("sample-bearer-token")

        val interceptor = AuthInterceptor(tokenManager)
        var capturedRequest: Request? = null

        val fakeChain = object : FakeInterceptorChain() {
            override fun proceed(request: Request): Response {
                capturedRequest = request
                return Response.Builder()
                    .request(request)
                    .protocol(Protocol.HTTP_1_1)
                    .code(200)
                    .message("OK")
                    .body("{}".toResponseBody("application/json".toMediaType()))
                    .build()
            }
        }

        interceptor.intercept(fakeChain)

        assertNotNull(capturedRequest)
        assertEquals("Bearer sample-bearer-token", capturedRequest?.header("Authorization"))
        assertEquals("application/json", capturedRequest?.header("Accept"))
    }

    @Test
    fun interceptor_clearsTokenAndNotifies_on401Unauthorized() {
        val tokenManager = FakeTokenManager()
        tokenManager.saveToken("expired-token")
        var unauthorizedCalled = false

        val interceptor = AuthInterceptor(tokenManager) {
            unauthorizedCalled = true
        }

        val fakeChain = object : FakeInterceptorChain() {
            override fun proceed(request: Request): Response {
                return Response.Builder()
                    .request(request)
                    .protocol(Protocol.HTTP_1_1)
                    .code(401)
                    .message("Unauthorized")
                    .body("{}".toResponseBody("application/json".toMediaType()))
                    .build()
            }
        }

        val response = interceptor.intercept(fakeChain)

        assertEquals(401, response.code)
        assertNull(tokenManager.getToken())
        assertTrue(unauthorizedCalled)
    }

    private class FakeTokenManager : TokenManager(android.content.ContextWrapper(null)) {
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

    private open class FakeInterceptorChain : Interceptor.Chain {
        override fun request(): Request = Request.Builder().url("http://localhost/api/v1/auth/me").build()
        override fun proceed(request: Request): Response = error("Not implemented")
        override fun connection(): Connection? = null
        override fun call(): Call = error("Not implemented")
        override fun connectTimeoutMillis(): Int = 0
        override fun withConnectTimeout(timeout: Int, unit: TimeUnit): Interceptor.Chain = this
        override fun readTimeoutMillis(): Int = 0
        override fun withReadTimeout(timeout: Int, unit: TimeUnit): Interceptor.Chain = this
        override fun writeTimeoutMillis(): Int = 0
        override fun withWriteTimeout(timeout: Int, unit: TimeUnit): Interceptor.Chain = this
    }
}
