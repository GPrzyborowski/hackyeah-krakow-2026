package com.example.momjobs.ui.screens

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowForward
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import coil.compose.AsyncImage
import com.example.momjobs.R

data class BlogArticle(
    val id: Int,
    val title: String,
    val summary: String,
    val author: String,
    val date: String,
    val imageUrl: String
)

val mockArticles = listOf(
    BlogArticle(
        1,
        "Powrót do pracy po przerwie",
        "Powrót do pracy po urlopie macierzyńskim może być wyzwaniem. Oto kilka wskazówek, które ułatwią ten proces.",
        "dr Emily Smith",
        "24 paź 2023",
        "https://images.unsplash.com/photo-1584622650111-993a426fbf0a?q=80&w=1000&auto=format&fit=crop"
    ),
    BlogArticle(
        2,
        "Jak napisać CV po macierzyńskim?",
        "Jak wyjaśnić luki w zatrudnieniu i podkreślić umiejętności nabyte podczas opieki nad dzieckiem.",
        "Jessica Williams",
        "02 lis 2023",
        "https://images.unsplash.com/photo-1586281380349-632531db7ed4?q=80&w=1000&auto=format&fit=crop"
    ),
    BlogArticle(
        3,
        "Praca zdalna dla mam",
        "Poznaj różne opcje pracy zdalnej, które oferują elastyczność niezbędną do pogodzenia kariery i życia rodzinnego.",
        "Sarah Johnson",
        "15 lis 2023",
        "https://images.unsplash.com/photo-1587620962725-abab7fe55159?q=80&w=1000&auto=format&fit=crop"
    ),
    BlogArticle(
        4,
        "Twoje prawa: ciąża i macierzyństwo",
        "Zrozumienie ochrony prawnej i przysługujących Ci uprawnień w czasie ciąży i po powrocie z urlopu.",
        "Maria Garcia",
        "01 gru 2023",
        "https://images.unsplash.com/photo-1450101499163-c8848c66ca85?q=80&w=1000&auto=format&fit=crop"
    )
)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun BlogScreen() {
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(stringResource(R.string.blog_title)) },
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
                .padding(innerPadding),
            contentPadding = PaddingValues(16.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            items(mockArticles) { article ->
                BlogItem(article = article)
            }
        }
    }
}

@Composable
fun BlogItem(article: BlogArticle) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .clickable { /* Navigate to detail */ },
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
        shape = MaterialTheme.shapes.extraLarge
    ) {
        Column {
            AsyncImage(
                model = article.imageUrl,
                contentDescription = null,
                modifier = Modifier
                    .fillMaxWidth()
                    .height(180.dp)
                    .clip(MaterialTheme.shapes.extraLarge),
                contentScale = ContentScale.Crop
            )
            Column(modifier = Modifier.padding(16.dp)) {
                Text(
                    text = article.title,
                    style = MaterialTheme.typography.titleLarge,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.primary
                )
                Spacer(modifier = Modifier.height(4.dp))
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text(
                        text = "Autor: ${article.author}",
                        style = MaterialTheme.typography.labelMedium,
                        color = MaterialTheme.colorScheme.secondary
                    )
                    Text(
                        text = article.date,
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.outline
                    )
                }
                Spacer(modifier = Modifier.height(8.dp))
                Text(
                    text = article.summary,
                    style = MaterialTheme.typography.bodyMedium,
                    maxLines = 3,
                    overflow = TextOverflow.Ellipsis
                )
                Spacer(modifier = Modifier.height(12.dp))
                TextButton(
                    onClick = { /* Navigate to detail */ },
                    contentPadding = PaddingValues(0.dp)
                ) {
                    Text(stringResource(R.string.read_more))
                    Spacer(modifier = Modifier.width(4.dp))
                    Icon(Icons.AutoMirrored.Filled.ArrowForward, contentDescription = null, modifier = Modifier.size(16.dp))
                }
            }
        }
    }
}
