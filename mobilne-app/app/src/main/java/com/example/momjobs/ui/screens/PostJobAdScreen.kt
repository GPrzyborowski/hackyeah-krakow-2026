package com.example.momjobs.ui.screens

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AddBusiness
import androidx.compose.material.icons.filled.Business
import androidx.compose.material.icons.filled.Description
import androidx.compose.material.icons.filled.LocationOn
import androidx.compose.material.icons.filled.Payments
import androidx.compose.material.icons.filled.Work
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import com.example.momjobs.R
import com.example.momjobs.ui.viewmodels.JobViewModel

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun PostJobAdScreen(
    viewModel: JobViewModel,
    onPostSuccess: () -> Unit
) {
    var title by remember { mutableStateOf("") }
    var company by remember { mutableStateOf("") }
    var description by remember { mutableStateOf("") }
    var requirements by remember { mutableStateOf("") }
    var location by remember { mutableStateOf("") }
    var salary by remember { mutableStateOf("") }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(stringResource(R.string.post_job_title)) },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = MaterialTheme.colorScheme.primaryContainer,
                    titleContentColor = MaterialTheme.colorScheme.onPrimaryContainer
                )
            )
        }
    ) { innerPadding ->
        LazyColumn(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding)
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            item {
                Icon(
                    imageVector = Icons.Default.AddBusiness,
                    contentDescription = null,
                    modifier = Modifier.size(64.dp),
                    tint = MaterialTheme.colorScheme.primary
                )
            }

            item {
                OutlinedTextField(
                    value = title,
                    onValueChange = { title = it },
                    label = { Text(stringResource(R.string.job_title)) },
                    modifier = Modifier.fillMaxWidth(),
                    leadingIcon = { Icon(Icons.Default.Work, contentDescription = null) },
                    singleLine = true
                )
            }

            item {
                OutlinedTextField(
                    value = company,
                    onValueChange = { company = it },
                    label = { Text(stringResource(R.string.company_name)) },
                    modifier = Modifier.fillMaxWidth(),
                    leadingIcon = { Icon(Icons.Default.Business, contentDescription = null) },
                    singleLine = true
                )
            }

            item {
                OutlinedTextField(
                    value = description,
                    onValueChange = { description = it },
                    label = { Text(stringResource(R.string.job_description)) },
                    modifier = Modifier.fillMaxWidth(),
                    leadingIcon = { Icon(Icons.Default.Description, contentDescription = null) },
                    minLines = 4
                )
            }

            item {
                OutlinedTextField(
                    value = requirements,
                    onValueChange = { requirements = it },
                    label = { Text(stringResource(R.string.requirements)) },
                    modifier = Modifier.fillMaxWidth(),
                    minLines = 3
                )
            }

            item {
                OutlinedTextField(
                    value = location,
                    onValueChange = { location = it },
                    label = { Text(stringResource(R.string.location_hint)) },
                    modifier = Modifier.fillMaxWidth(),
                    leadingIcon = { Icon(Icons.Default.LocationOn, contentDescription = null) },
                    singleLine = true
                )
            }

            item {
                OutlinedTextField(
                    value = salary,
                    onValueChange = { salary = it },
                    label = { Text(stringResource(R.string.salary_range)) },
                    modifier = Modifier.fillMaxWidth(),
                    leadingIcon = { Icon(Icons.Default.Payments, contentDescription = null) },
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                    singleLine = true
                )
            }

            item {
                Button(
                    onClick = {
                        if (title.isNotBlank() && company.isNotBlank()) {
                            viewModel.postJob(
                                title = title,
                                company = company,
                                description = description,
                                requirements = requirements,
                                location = location,
                                salary = salary
                            )
                            onPostSuccess()
                        }
                    },
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(56.dp),
                    shape = MaterialTheme.shapes.extraLarge
                ) {
                    Text(stringResource(R.string.post_job_button), style = MaterialTheme.typography.titleMedium)
                }
            }
        }
    }
}
