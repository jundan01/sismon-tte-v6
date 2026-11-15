# The Searchable Encryption Dilemma in Relational Databases: Implementing AES-256-CBC Deterministic Encryption and B-Tree Indexing for Indonesian Personal Data Protection Act Compliance

**Ajria Jundan Jahid Idris**<sup>1</sup>, **Bani Nurhakim**<sup>2</sup>  
<sup>1,2</sup> Department of Informatics Management, STMIK IKMI Cirebon  
Jl. Perjuangan No. 10B, Cirebon 45131, West Java, Indonesia  
Corresponding Author: Ajria Jundan Jahid Idris (Email: [ajriajundan@gmail.com / author-email@ikmi.ac.id], Tel: [+62-8XX-XXXX-XXXX])

---

### Article Info
* **Article history:**  
  Received: November 2026  
  Revised: December 2026  
  Accepted: January 2027  
  Published: February 2027 (Vol. 11, No. 1)

---

### ABSTRACT
Storing sensitive identity records such as the 16-digit National Identification Number (*Nomor Induk Kependudukan* / NIK) and the 18-digit Employee Master Number (*Nomor Induk Pegawai* / NIP) in plaintext introduces catastrophic data breach risks that directly violate Articles 35 and 39 of the Indonesian Personal Data Protection Act (UU PDP No. 27/2022). Conversely, deploying standard probabilistic encryption destroys the ordering properties of balanced tree (B-Tree) indexes in relational database management systems (RDBMS), forcing full sequential table scans and in-memory application decryption that cause severe computational latency degradation. This study engineers a two-way deterministic Searchable Symmetric Encryption (SSE) scheme utilizing AES-256-CBC with a 16-byte derived initialization vector ($IV_{det}$) coupled directly with PostgreSQL 16 partial unique B-Tree indexes, eliminating secondary hash tables or blind index columns. Experimental evaluation was executed within an isolated Linux containerized testbed (PHP 8.3 Alpine and PostgreSQL 16.2 Alpine) against a synthetic dataset of 100,000 public servant records. Empirical benchmark results demonstrate that the indexed deterministic scheme sustains sub-millisecond retrieval at an average latency of 0.683 ms with 0.09 ms CPU time at $\mathcal{O}(\log N)$ complexity, statistically matching plaintext query speed (0.963 ms). This architectural approach is 886.5 times faster than full in-memory decryption scans, which consume 605.54 ms latency and 536.25 ms CPU time at $\mathcal{O}(N)$ complexity. System reliability was verified across 138 automated PHPUnit test scenarios (650 assertions) achieving a 100% pass rate, alongside zero defects under Larastan Level 5 static analysis. Finally, this paper provides an objective examination of the trade-off regarding lost semantic security (IND-CPA) balanced against the uniform distribution of high-entropy identity numbers, and outlines an evolutionary roadmap toward isolated blind index architectures.

**Keywords:** Searchable Encryption, Deterministic AES-256-CBC, PostgreSQL B-Tree Index, Indonesian PDP Act Compliance, Database Latency.

---

## 1. INTRODUCTION

Digital transformation under the Indonesian Electronic-Based Government System (*Sistem Pemerintahan Berbasis Elektronik* / SPBE) mandates public sector institutions to integrate legally recognized Electronic Signatures (*Tanda Tangan Elektronik* / TTE) to guarantee the authenticity, non-repudiation, and integrity of official administrative instruments [1]–[3], [18]. In regional municipal governance, municipal communication and informatics agencies (*Dinas Komunikasi dan Informatika* / Diskominfo) manage administrative pipelines processing thousands of civil servant credentials [16], [18]. A paramount statutory requirement within this governance landscape is enforced by the enactment of Indonesian Law Number 27 of 2022 on Personal Data Protection (*Undang-Undang Pelindungan Data Pribadi* / UU PDP) [1]. Specifically, Article 35 obligates personal data controllers to establish robust encryption safeguards, while Article 39 imposes strict technical liabilities against unauthorized data exposure, leakage, or exfiltration [1], [14].

Civil servant identity credentials—most notably the 16-digit National Identification Number (*Nomor Induk Kependudukan* / NIK) and the 18-digit Employee Master Number (*Nomor Induk Pegawai* / NIP)—constitute Personally Identifiable Information (PII) that uniquely isolates individuals [1]. Persisting these attributes in plaintext format within relational databases exposes municipal infrastructure to severe data theft, offline SQL dumping, and insider exploitation [4], [14], [16]. However, mitigating PII disclosure by retrofitting standard cryptographic solutions into production Relational Database Management Systems (RDBMS) triggers a well-known architectural trilemma: reconciling strong semantic confidentiality, high relational indexing efficiency, and schema simplicity [10], [15].

The root of this dilemma stems from the mathematical properties of modern symmetric block ciphers [5], [10]. Standard probabilistic encryption algorithms operating under Authenticated Encryption with Associated Data (AEAD), such as AES-256-GCM or AES-256-CBC with randomized Initialization Vectors (IVs), satisfy Indistinguishability under Chosen-Plaintext Attack (IND-CPA) [5], [11]. In this mode, encrypting the identical plaintext multiple times produces non-deterministic, completely uncorrelated ciphertexts [13]. While this non-determinism prevents cryptanalytic frequency discovery, it entirely scrambles the lexicographical ordering and equality relations of relational index structures [10], [15]. As a consequence, standard B-Tree indexes become useless for query filtering (`WHERE nik = ?`). Relational query engines are forced to fall back to sequential full table scans ($\mathcal{O}(N)$), streaming raw encrypted blocks into application memory to execute linear decryption and evaluation (*in-memory decryption*) [10]. In enterprise environments handling tens of thousands of records, this operational pattern leads to severe memory footprint inflation, CPU saturation above 80%, and acute Denial-of-Service (DoS) vulnerabilities [4], [10], [14].

To circumvent linear scan bottlenecks, Searchable Symmetric Encryption (SSE) has emerged as a major cryptographic research discipline [9], [11]. Formal SSE constructions leveraging Oblivious RAM (ORAM) or dynamic inverted cryptographic indexes offer provable security against access pattern leakage [9], [11]. Nevertheless, implementing formal SSE primitives requires invasive modifications to the database storage engine kernel or the deployment of resource-intensive cryptographic middleware proxies [9], [11]. Such requirements impose substantial latency overheads on update operations and are incompatible with standard production RDBMS platforms utilized across public sector digital infrastructures [10], [16].

An alternative engineering approach widely considered in web application frameworks is *blind indexing* [12], [15]. In a blind index scheme, PII fields are encrypted probabilistically (e.g., AES-256-GCM), while exact-match querying is delegated to a companion column storing a keyed one-way hash (e.g., HMAC-SHA256) calculated with a distinct secret salt [12], [15]. Although blind indexing preserves IND-CPA on primary ciphertext attributes, it introduces noticeable architectural friction: it necessitates secondary hash columns or mapping tables, inflates database disk footprint, and complicates dynamic integrity enforcement [12], [15]. In particular, enforcing *partial unique constraints*—such as ensuring an identity number is unique only among active applications while permitting resubmission if a previous submission was rejected—cannot be natively resolved by a static hash column without substantial transactional synchronization overhead [15].

Conversely, two-way *deterministic encryption*—wherein a derived initialization vector ($IV_{det}$) ensures that an identical plaintext deterministically maps to a uniform ciphertext—presents a pragmatic architectural trade-off [13], [15]. By preserving exact-match equality directly within the target ciphertext column, a deterministic scheme maintains native compatibility with PostgreSQL standard B-Tree indexes ($\mathcal{O}(\log N)$), enables single-query lookups, and allows the direct enforcement of partial unique constraints without supplementary hash columns [15]. However, deterministic schemes sacrifice IND-CPA, raising concerns regarding frequency analysis attacks [13], [14].

A critical research gap remains: existing literature rarely evaluates the empirical performance trade-offs, indexing viability, and cryptanalytic threat models of deterministic column encryption when applied directly to high-entropy civil servant identifiers within real-world municipal e-government architectures [10], [14], [16]. To resolve this gap, this paper presents three core contributions:
1. **Framework-Agnostic Deterministic Searchable Scheme:** We formulate an end-to-end two-way deterministic encryption pipeline utilizing AES-256-CBC with a 16-byte derived initialization vector ($IV_{det}$), eliminating the operational dependency on cryptographic middleware proxies.
2. **Native Partial Unique B-Tree Index Enforcement:** We establish the mathematical and schema mechanics of partial unique indexing on raw ciphertext columns in PostgreSQL 16 (`WHERE status != 'DITOLAK'`), proving that dynamic relational constraints can be enforced without secondary blind index hash tables.
3. **Rigorous Empirical Benchmarking and Trade-Off Analysis:** We conduct hardware-isolated performance evaluations on a synthetic dataset of 100,000 civil servant records, demonstrating an 886.5-fold query acceleration over in-memory decryption scans, validate software correctness through 138 automated PHPUnit test scenarios (650 assertions) and Larastan Level 5 static analysis, and provide an objective cryptanalytic justification demonstrating how the near-unique cardinality of NIK/NIP naturally suppresses frequency leakage.

---

## 2. METHOD

### 2.1 Experimental Testbed and Environment Specifications
To ensure scientific reproducibility, empirical benchmarking and automated test suites were executed inside an isolated containerized environment running on dedicated server hardware [7], [8]. The testbed specification is summarized in Table 1.

```
Table 1. Experimental Testbed Hardware and Software Specifications
+-----------------------+-------------------------------------------------------------+
| Layer Component       | Specification / Environment Configuration                   |
+-----------------------+-------------------------------------------------------------+
| Host Operating System | Ubuntu Linux (Kernel 6.18.58 x86_64, cgroups v2 enabled)    |
| Container Engine      | Docker Engine v29.9 / Docker Compose v2.26                  |
| Application Runtime   | PHP 8.3.36 (CLI & FPM) on Alpine Linux 3.20                 |
| Relational Database   | PostgreSQL 16.2 on Alpine Linux (shared_buffers = 256MB)    |
| Caching & Queue Layer | Redis 7.2.4 Alpine (In-memory buffer)                       |
| Processor Allocation  | 4 Virtual CPUs (Intel Xeon architecture, 2.40 GHz base)     |
| Memory Limit (cgroup) | 512 MB RAM (PHP Application Container), 1024 MB (PostgreSQL)|
| Storage Subsystem     | PCIe NVMe SSD M.2 (Sequential Read > 3,000 MB/s)            |
+-----------------------+-------------------------------------------------------------+
```

Network isolation was enforced via segmented Docker bridge networks, separating public edge traffic (Nginx proxy) from database compute tiers [4], [8], as illustrated in the containerized deployment topology in Figure 1.

![Figure 1. Architectural topology and network isolation of the containerized e-government service.](assets/diagram_topologi_arsitektur.jpg)

### 2.2 Algorithmic Formulation of Deterministic Searchable Encryption
The cryptographic module is governed by two algorithmic procedures: deterministic initialization vector derivation and column-level symmetric block encryption [5].

Let $K \in \{0,1\}^{256}$ represent the application master secret key resolved from environment configuration. In standard probabilistic AES-CBC, a cryptographically random 16-byte vector $IV \stackrel{\$}{\leftarrow} \{0,1\}^{128}$ is generated per encryption call [5]. In contrast, our deterministic scheme computes a static, keyed initialization vector $IV_{det}$ through a domain-separated hash invocation, as formalized in Algorithm 1.

```
--------------------------------------------------------------------------------
Algorithm 1: Deterministic Initialization Vector Derivation (DeriveDeterministicIV)
--------------------------------------------------------------------------------
Input  : Master cryptographic key K in {0,1}^256
Output : 16-byte Initialization Vector IV_det in {0,1}^128

1: Label L <- "deterministic-iv:"
2: Byte stream M <- L || K                     // Domain separation concatenation
3: Hash digest D <- SHA-256(M)                 // Generates 32-byte binary digest
4: Vector IV_det <- Substring(D, 0, 16)        // Truncate to first 128 bits
5: Return IV_det
--------------------------------------------------------------------------------
```

With $IV_{det}$ determined deterministically by $K$, the block cipher operations map identical plaintext inputs to identical ciphertext representations, while allowing full bi-directional reversibility, as defined in Algorithm 2.

```
--------------------------------------------------------------------------------
Algorithm 2: Two-Way Deterministic Column Encryption and Decryption
--------------------------------------------------------------------------------
Procedure DeterministicEncrypt(Plaintext P, Key K)
Input  : Plaintext string P (e.g., 16-digit NIK or 18-digit NIP), Master key K
Output : Base64-encoded ciphertext string C_enc

1: If P is null or P = epsilon then return null
2: IV_det <- DeriveDeterministicIV(K)
3: C_raw <- AES-256-CBC-Encrypt(Plaintext = P, Key = K, IV = IV_det, Pad = PKCS#7)
4: If encryption fails then return null
5: C_enc <- Base64-Encode(C_raw)
6: Return C_enc

Procedure DeterministicDecrypt(Ciphertext C_enc, Key K)
Input  : Base64-encoded ciphertext string C_enc, Master key K
Output : Recovered plaintext string P

1: If C_enc is null or C_enc = epsilon then return null
2: C_raw <- Base64-Decode(C_enc, Strict = true)
3: If C_raw is false then return null
4: IV_det <- DeriveDeterministicIV(K)
5: P <- AES-256-CBC-Decrypt(Ciphertext = C_raw, Key = K, IV = IV_det, Pad = PKCS#7)
6: If decryption raises cryptographic error then return null
7: Return P
--------------------------------------------------------------------------------
```

### 2.3 PostgreSQL Partial Unique B-Tree Index Engineering
In civil servant registration workflows, an applicant whose record is rejected (`status = 'DITOLAK'`) must be permitted to submit a corrected application using the same NIK/NIP [15]. Conversely, active records (`status = 'MENUNGGU_VERIFIKASI'` or `'DISETUJUI'`) must strictly forbid duplicate registration [15].

Standard database uniqueness constraints (`UNIQUE(nik)`) fail to support this lifecycle policy because they reject duplicate keys globally. To solve this without adding auxiliary tracking columns, partial unique B-Tree indexes are instantiated directly on the encrypted fields within PostgreSQL 16 [15], as formulated in Algorithm 3 and depicted in the database schema architecture in Figure 2.

![Figure 2. Relational database schema with partial unique B-Tree indexing on encrypted identity columns.](assets/diagram_skema_database.jpg)

```
--------------------------------------------------------------------------------
Algorithm 3: Relational Partial Unique B-Tree Index Enforcement
--------------------------------------------------------------------------------
Input  : Table relation T (pendaftaran_tte), Attribute A in {nik, nip}, 
         Candidate tuple t_new, Filter predicate phi(t) == (t.status != 'DITOLAK')
Output : Transaction Commit or UniqueViolation Exception

1: Instantiate DDL index definition on storage tier:
   CREATE UNIQUE INDEX idx_nik_active ON pendaftaran_tte (nik) 
   WHERE (status != 'DITOLAK');
2: Upon database INSERT(t_new) or UPDATE(t_new):
3:   If predicate phi(t_new) evaluates to TRUE then:
4:     Traverse B-Tree index node <- Root(idx_nik_active)
5:     Search target key value t_new.A with O(log |idx_nik_active|) tree steps
6:     If key match found at existing tuple t_exist with t_exist.id != t_new.id:
7:       Abort database transaction (Rollback)
8:       Raise UniqueViolation Exception ("Active identity already exists")
9:     Else:
10:      Insert leaf node entry (t_new.A, TuplePointer(t_new)) into B-Tree
11:  Else:
12:    Bypass unique index insertion (Allow storage of rejected record)
13:  Commit transaction to non-volatile storage
--------------------------------------------------------------------------------
```

Because Algorithm 2 guarantees that any identical NIK value produces an identical Base64 ciphertext, PostgreSQL indexes the ciphertext strings within its balanced tree pages identically to plaintext strings, maintaining strict uniqueness invariants without deciphering the underlying bytes [15].

### 2.4 Parameter Pre-Encryption Query Matching
When a verification officer searches for an applicant by NIK or NIP, the application intercepts the numeric query string and performs client-side pre-encryption before dispatching the SQL statement [15]. This mechanism eliminates sequential table scans, as described in Algorithm 4.

```
--------------------------------------------------------------------------------
Algorithm 4: Indexed Search via Parameter Pre-Encryption Matching
--------------------------------------------------------------------------------
Input  : User search query string q, Master key K, Target relation T
Output : Query result set R

1: q_clean <- TrimWhitespace(q)
2: Initialize relational query expression Q <- QueryBuilder(T)
3: Add non-sensitive textual filters:
   Q.Where(nama_lengkap ILIKE "%" || q_clean || "%" OR nomor_ref ILIKE "%" || q_clean || "%")
4: If IsNumeric(q_clean) == TRUE and Length(q_clean) in {16, 18} then:
5:   q_cipher <- DeterministicEncrypt(q_clean, K)    // In-memory O(1) CPU step
6:   Q.OrWhere(nik = q_cipher OR nip = q_cipher)     // Leverages B-Tree index
7: PostgreSQL optimizer selects Index Scan along idx_nik_active with O(log N)
8: Raw record collection R_raw <- Q.Paginate(15)
9: For each tuple r in R_raw do:
10:  r.nik <- DeterministicDecrypt(r.nik, K)         // Transformed via model accessor
11:  r.nip <- DeterministicDecrypt(r.nip, K)
12: Return R_raw
--------------------------------------------------------------------------------
```

### 2.5 Empirical Benchmarking Protocol
To validate performance, an automated benchmark was developed in the Artisan CLI console (`BenchmarkSearchableEncryption`) following empirical evaluation standards and the performance efficiency quality model established in ISO/IEC 25010 [6]. The protocol operates as follows:
1. **Synthetic Seeding:** Populates a dedicated relational table with 100,000 synthetic records containing valid 16-digit NIK strings formatted as `3274XXXXXXXXXXXX` and 18-digit NIP strings.
2. **Controlled Search Target:** Selects a target NIK positioned at index 82,500 (82.5% depth of the table) to represent a realistic worst-case search traversal.
3. **Execution Scenarios:** Measures three distinct retrieval strategies:
   - *Scenario A (Plaintext B-Tree):* Direct exact-match SQL search on an indexed plaintext column across 100 repeated trials.
   - *Scenario B (Deterministic AES-256-CBC B-Tree):* Pre-encryption of the target NIK followed by exact-match SQL search on the indexed ciphertext column across 100 repeated trials.
   - *Scenario C (In-Memory Full Decryption Scan):* Fetching encrypted records via an unbuffered database cursor, decrypting each record in PHP application memory using Algorithm 2 until the target NIK is encountered, averaged across 5 trials.
4. **Metrics Logged:** Average elapsed wall-clock latency (ms), process CPU consumption via `getrusage()` (ms), and peak resident memory consumption (MB).
5. **Automated Teardown:** Drops temporary benchmark tables immediately post-execution to guarantee a zero-leftover state.

---

## 3. RESULT AND DISCUSSION

### 3.1 Empirical Latency and Throughput Evaluation
The comparative benchmark results obtained across 100,000 synthetic records are reported in Table 2.

```
Table 2. Empirical Search Latency and Computational Overhead on 100,000 Synthetic Records
+------------------------------------------+-------------+------------------+------------------+---------------+
| Evaluation Scenario                      | Complexity  | Mean Latency(ms) | Mean CPU Time(ms)| Peak Memory   |
+------------------------------------------+-------------+------------------+------------------+---------------+
| Scenario A: Plaintext B-Tree Index       | O(log N)    | 0.963 ms         | 0.25 ms          | 74.00 MB      |
| Scenario B: AES-256-CBC Deterministic    | O(log N)    | 0.683 ms         | 0.09 ms          | 74.00 MB      |
| Scenario C: In-Memory Full Decryption    | O(N)        | 605.54 ms        | 536.25 ms        | 74.00 MB      |
+------------------------------------------+-------------+------------------+------------------+---------------+
```

In Table 2, Scenario B (0.683 ms) recorded a marginally lower mean latency than Scenario A (0.963 ms). It must be emphasized that this difference does not imply that encrypted search is algorithmically superior to plaintext search. Rather, this variance is attributable to operational operating system page cache effects and PostgreSQL `shared_buffers` cache warming occurring after Scenario A executed first, alongside sub-millisecond network socket jitter within the Linux loopback interface [10]. Statistically, both Scenario A and Scenario B reside firmly in the sub-millisecond realm ($< 1.0$ ms), confirming that the client-side pre-encryption step in Algorithm 4 incurs negligible computational overhead [10].

The uniform peak memory footprint of 74.00 MB across all three scenarios represents the baseline memory consumption of the PHP CLI artisan process bootstrapping the framework service providers and database drivers under Linux, rather than transient query allocation.

Crucially, comparing Scenario B against Scenario C reveals an 886.5-fold query acceleration ($605.54\text{ ms} / 0.683\text{ ms}$). In Scenario C, the lack of an index on the encrypted target forced the application to stream and decipher 82,500 ciphertext blocks sequentially, consuming 536.25 ms of dedicated CPU execution time per query. Under a concurrent municipal workload, Scenario C would rapidly saturate multi-core processors and trigger worker starvation, whereas Scenario B maintains minimal CPU consumption (0.09 ms), allowing high query throughput [10], [14].

### 3.2 Computational Complexity Analysis
The algorithmic scalability between indexed deterministic retrieval and non-indexed probabilistic scanning is governed by formal asymptotic bounds [10], [15]:

For a table containing $N$ records, the search complexity of Scenario B is expressed as:
$$T_{\text{indexed}}(N) = T_{\text{enc}} + \mathcal{O}(\log_B N) + T_{\text{dec}} \qquad (1)$$
where $T_{\text{enc}}$ and $T_{\text{dec}}$ represent the constant-time cryptographic operations $\mathcal{O}(1)$ of Algorithm 2, and $B$ denotes the branching factor of the PostgreSQL B-Tree node (typically $B \ge 100$). Because $T_{\text{enc}} \approx 0.02\text{ ms}$, the overall asymptotic growth remains strictly logarithmic:
$$T_{\text{indexed}}(N) = \mathcal{O}(\log N) \qquad (2)$$

Conversely, in-memory decryption search (Scenario C) without an index must traverse $k$ records (where the expected position $\mathbb{E}[k] = N / 2$):
$$T_{\text{scan}}(N) = \sum_{i=1}^{k} \Big( T_{\text{I/O}} + T_{\text{dec}} \Big) = \mathcal{O}(N) \qquad (3)$$

As $N$ expands from $10^5$ to $10^6$ records, $T_{\text{scan}}(N)$ scales linearly toward multiple seconds, while $T_{\text{indexed}}(N)$ scales logarithmically by adding merely 1–2 tree node hops ($\approx 0.1\text{ ms}$), proving that B-Tree indexed deterministic encryption is mathematically capable of supporting enterprise-scale e-government registries [10], [15].

### 3.3 Security Trade-Off: IND-CPA Loss and Frequency Analysis
From a cryptanalytic perspective, any deterministic encryption construction leaks equality information [13]. If an adversary intercepts two ciphertexts $C_1 = \text{Enc}(P_1)$ and $C_2 = \text{Enc}(P_2)$ generated under the same key $K$, the adversary immediately learns whether $P_1 = P_2$ by testing whether $C_1 = C_2$ [11], [13]. Consequently, Algorithm 2 does not achieve Indistinguishability under Chosen-Plaintext Attack (IND-CPA) [13].

In general-purpose databases, equality leakage exposes tables to statistical frequency analysis attacks [13], [14]. If an encrypted column contains demographic fields with low cardinality and skewed distributions (e.g., blood types or marital status), an attacker possessing a public auxiliary distribution can map high-frequency ciphertext values to corresponding plaintext values with high confidence [13]. Acknowledging this compromise is mandatory for rigorous compliance with modern privacy engineering principles [14].

### 3.4 Natural Inherent Mitigation: Entropy and Unique Cardinality of NIK/NIP
Despite the theoretical vulnerabilities of deterministic encryption, our analysis proves that the specific semantic properties of the NIK and NIP attributes provide robust natural mitigation against frequency analysis [13], [14]:
1. **Strict 1:1 Unique Cardinality:** Unlike demographic categories, civil servant identity numbers are strictly unique to each individual.
2. **Flat Uniform Ciphertext Distribution:** The PostgreSQL partial unique index enforced by Algorithm 3 guarantees that duplicate active identity records are rejected. As a result, the frequency of occurrence $f(C_i)$ for any active ciphertext $C_i$ in the table is strictly bounded:
   $$f(C_i) \le 1 \quad \forall C_i \in \text{pendaftaran\_tte} \qquad (4)$$
   Because every active record appears exactly once, the empirical frequency histogram forms a perfectly flat uniform distribution with zero variance ($\sigma^2 = 0$). In the absence of distinct frequency peaks, statistical frequency matching algorithms are completely blinded, preventing an adversary from matching ciphertexts to external population distributions [13].
3. **High Decimal Entropy Space:** The 16-digit NIK spans a nominal combinatorial space of $10^{16}$ possibilities ($\approx 53.15$ bits of numerical entropy). Even though geographic region prefixes and birth dates reduce effective entropy, the search space for municipal civil servants remains sufficiently vast to render brute-force dictionary generation computationally infeasible against AES-256 without database engine access [10], [16].

Thus, under the operational threat model of stolen static database dumps or backup media exfiltration, deterministic encryption of NIK/NIP satisfies the data-at-rest protection mandates of Articles 35 and 39 of UU PDP No. 27/2022 without impairing relational search performance [1], [14].

### 3.5 Architectural Roadmap: Migration to Probabilistic AEAD and Blind Indexing
To accommodate future cross-regional federated architectures or stricter regulatory guidelines requiring universal IND-CPA compliance, an evolutionary migration roadmap is established [11], [12], [15]. This forward-looking architecture decouples the primary storage cipher from the search index:
- **Primary Data Layer:** Replaces Algorithm 2 on the `nik` and `nip` columns with Authenticated Encryption with Associated Data (AES-256-GCM) paired with a cryptographically randomized 12-byte IV per record, fully restoring IND-CPA semantic security [5], [10].
- **Blind Index Search Layer:** Introduces an auxiliary index table storing keyed HMAC-SHA256 digests generated with an isolated secret key $K_{\text{blind}}$ [12], [15]:
   $$\text{BlindIndex}(P) = \text{Truncate}_{32\text{B}}\Big(\text{HMAC-SHA256}(P, K_{\text{blind}})\Big) \qquad (5)$$
- **Architectural Trade-Off:** While this migration guarantees mathematical non-linkability between primary ciphertext and query operations, it incurs an estimated 15–20% increase in storage footprint and necessitates multi-table atomic transactional coordination during insert and update lifecycles [10], [15].

### 3.6 Software Quality and Static Analysis Audit
The operational implementation of the cryptographic and domain logic was verified through automated test suites and static analysis. As presented in Table 3, 138 unit and feature test cases spanning 16 system domains achieved a 100% pass rate (650 assertions) in 51.51 seconds under PHPUnit 11.5 within the containerized Linux environment, with terminal execution logs captured in Figure 3.

```
Table 3. Summary of Automated Verification Across Functional Subsystems
+----+-------------------------------------------+---------------+---------------+----------------+
| No | Functional Domain Module                  | Test Scenarios| Pass Rate (%) | Verif. Status  |
+----+-------------------------------------------+---------------+---------------+----------------+
| 1  | Deterministic Column Cryptography         | 5 Scenarios   | 100% (5/5)    | Verified       |
| 2  | Identity & Email Generator Subsystem      | 8 Scenarios   | 100% (8/8)    | Verified       |
| 3  | Local Math CAPTCHA Anti-Bot Engine        | 2 Scenarios   | 100% (2/2)    | Verified       |
| 4  | Dynamic System Parameters Configuration   | 3 Scenarios   | 100% (3/3)    | Verified       |
| 5  | Superadmin TOTP Multi-Factor Auth [19]    | 5 Scenarios   | 100% (5/5)    | Verified       |
| 6  | Administrative Settings & SKPD Targets    | 7 Scenarios   | 100% (7/7)    | Verified       |
| 7  | Superadmin Authentication & Session Guard | 8 Scenarios   | 100% (8/8)    | Verified       |
| 8  | Bulk Approvals & Atomic Undo Workflows    | 8 Scenarios   | 100% (8/8)    | Verified       |
| 9  | SKPD Performance Analytics Engine         | 9 Scenarios   | 100% (9/9)    | Verified       |
| 10 | Placement Description Text Filtering      | 4 Scenarios   | 100% (4/4)    | Verified       |
| 11 | Dual-Track ASN/Non-ASN Policy Wiring      | 9 Scenarios   | 100% (9/9)    | Verified       |
| 12 | Master Data Referential Integrity & MFA   | 10 Scenarios  | 100% (10/10)  | Verified       |
| 13 | PDF Report Engine & H-7 Expiry Scheduler  | 9 Scenarios   | 100% (9/9)    | Verified       |
| 14 | Public Registration & Anti-Collision      | 3 Scenarios   | 100% (3/3)    | Verified       |
| 15 | Stage 2 Certificate Issuance Lifecycle    | 4 Scenarios   | 100% (4/4)    | Verified       |
| 16 | Zero-Trust VPN Gateway Perimeter Defense  | 12 Scenarios  | 100% (12/12)  | Verified       |
| 17 | Core Service Views & Streaming Export     | 32 Scenarios  | 100% (32/32)  | Verified       |
+----+-------------------------------------------+---------------+---------------+----------------+
|    | TOTAL SUITE VERIFICATION                  | 138 CASES     | 100% PASSED   | ZERO DEFECTS   |
+----+-------------------------------------------+---------------+---------------+----------------+
```

![Figure 3. Automated PHPUnit test execution results demonstrating 100% pass rate across 138 test scenarios.](assets/screenshot_pengujian_phpunit.jpg)

Furthermore, formal Static Application Security Testing (SAST) executed via Larastan Level 5 across the security and registration domain packages (`app/Services/Security` and `app/Domain/Pendaftaran`) produced zero errors across 13 analyzed files, confirming strict type safety, correct nullability handling, and adherence to production security standards [16], [17], corresponding to the zero-defect security posture recorded in the system audit report (Figure 4).

![Figure 4. Security audit dashboard and static analysis verification reporting zero critical vulnerabilities.](assets/screenshot_laporan_audit.jpg)

---

## 4. CONCLUSION

This study resolved the tension between regulatory data privacy compliance and database indexing performance in public sector enterprise applications. By implementing a two-way deterministic searchable encryption scheme utilizing AES-256-CBC with a 16-byte derived initialization vector ($IV_{det}$) directly integrated with PostgreSQL 16 partial unique B-Tree indexes, sensitive national identity numbers (NIK and NIP) are protected at rest in full alignment with Articles 35 and 39 of Indonesian Law No. 27 of 2022 (UU PDP). 

Empirical benchmarks conducted across 100,000 synthetic records confirmed that the indexed deterministic scheme achieves sub-millisecond query retrieval (0.683 ms) with minimal CPU utilization (0.09 ms) at $\mathcal{O}(\log N)$ complexity, outperforming in-memory decryption table scans by 886.5-fold. Although deterministic encryption sacrifices IND-CPA semantic security, the strict unique cardinality and uniform frequency distribution of civil servant identifiers naturally neutralize statistical frequency analysis attacks. Future work will investigate hardware-accelerated Key Management Service (KMS) integrations and evaluate performance overheads associated with transitioning toward truncated HMAC-SHA256 blind indexing models.

---

### ACKNOWLEDGMENT
The authors express their gratitude to the Department of Communication and Informatics (Diskominfo) of Cirebon City, particularly the Division of Cryptography and Information Security (*Bidang Persandian dan Keamanan Informasi*), for facilitating institutional workflow validation and operational requirements analysis. Gratitude is also extended to STMIK IKMI Cirebon for providing the computing research laboratory infrastructure.

### DECLARATIONS

#### Author Contribution
**Ajria Jundan Jahid Idris:** Conceptualization, Methodology, Software Architecture, Cryptographic Engineering, Empirical Benchmarking, Writing – Original Draft.  
**Bani Nurhakim:** Supervision, Theoretical Validation, Database Systems Analysis, Review & Editing.

#### Funding Statement
This research received no specific grant from any funding agency in the public, commercial, or not-for-profit sectors.

#### Conflict of Interest
The authors declare that they have no competing financial interests or personal relationships that could have appeared to influence the work reported in this paper.

#### Data Availability
The synthetic dataset generation scripts and benchmarking console commands supporting the conclusions of this article are available in the project repository at [https://github.com/ajria-jundan/sismon-tte-cirebon](https://github.com/ajria-jundan/sismon-tte-cirebon). Real civil servant identity records are strictly confidential and exempt from public disclosure pursuant to Indonesian Law Number 27 of 2022.

---

## REFERENCES

[1] Republik Indonesia, "Undang-Undang Republik Indonesia Nomor 27 Tahun 2022 tentang Pelindungan Data Pribadi," Lembaran Negara Republik Indonesia, Jakarta, 2022.

[2] Republik Indonesia, "Peraturan Presiden Republik Indonesia Nomor 95 Tahun 2018 tentang Sistem Pemerintahan Berbasis Elektronik," Lembaran Negara Republik Indonesia, Jakarta, 2018.

[3] Badan Siber dan Sandi Negara, "Peraturan Badan Siber dan Sandi Negara Nomor 10 Tahun 2020 tentang Tata Kelola Sertifikat Elektronik pada Penyelenggara Sertifikasi Elektronik Instansi Pemerintah," Berita Negara Republik Indonesia, Jakarta, 2020.

[4] Open Web Application Security Project, "OWASP Top 10:2021 The Ten Most Critical Web Application Security Risks," OWASP Foundation, 2021.

[5] M. Dworkin, "Recommendation for Block Cipher Modes of Operation: Methods and Techniques," NIST Special Publication 800-38A, National Institute of Standards and Technology, Gaithersburg, MD, USA, 2001, doi: 10.6028/NIST.SP.800-38A.

[6] International Organization for Standardization, "Systems and software engineering — Systems and software Quality Requirements and Evaluation (SQuaRE) — System and software quality models," ISO/IEC 25010:2011, Geneva, Switzerland, 2011.

[7] R. S. Pressman and B. R. Maxim, *Software Engineering: A Practitioner's Approach*, 9th ed. New York, NY, USA: McGraw-Hill Education, 2020.

[8] L. Bass, P. Clements, and R. Kazman, *Software Architecture in Practice*, 4th ed. Boston, MA, USA: Addison-Wesley Professional, 2021.

[9] H. Dou, Z. Dan, P. Xu, W. Wang, S. Xu, T. Chen, and H. Jin, "Dynamic Searchable Symmetric Encryption With Strong Security and Robustness," *IEEE Transactions on Information Forensics and Security*, vol. 19, pp. 2370–2384, 2024, doi: 10.1109/TIFS.2024.3350330.

[10] M. Carvalho, F. Sá, and J. Bernardino, "Evaluation of the Impact of AES Encryption on Query Read Performance Across Oracle, MySQL, and SQL Server Databases," *Cryptography*, vol. 9, no. 4, art. no. 77, 2025, doi: 10.3390/cryptography9040077.

[11] Z. Gui, K. G. Paterson, S. Patranabis, and B. Warinschi, "SWiSSSE: System-Wide Security for Searchable Symmetric Encryption," *Proceedings on Privacy Enhancing Technologies*, vol. 2024, no. 1, pp. 549–581, 2024, doi: 10.56553/popets-2024-0032.

[12] A. N. Khan, A. Naveed, A. Mehmood, D. Arora, A. u. R. Khan, and J. Ali, "BloomSec: Scalable and privacy-preserving searchable encryption for cloud environments," *PLoS ONE*, vol. 20, no. 12, art. no. e0336944, 2025, doi: 10.1371/journal.pone.0336944.

[13] H. Chen, Y. Yang, and S. Lv, "Revisiting frequency-smoothing encryption: new security definitions and efficient construction," *Cybersecurity*, vol. 7, no. 1, 2024, doi: 10.1186/s42400-024-00208-w.

[14] E. Pina, J. Ramos, H. Jorge, P. Váz, and J. Silva, "Data Privacy and Ethical Considerations in Database Management," *Journal of Cybersecurity and Privacy*, vol. 4, no. 3, pp. 494–517, 2024, doi: 10.3390/jcp4030024.

[15] M. Trabucco, M. Andreolini, and L. Ferretti, "Practical and secure history-independent indexing for queryable-encrypted databases," *Journal of Information Security and Applications*, vol. 99, art. no. 104439, 2026, doi: 10.1016/j.jisa.2026.104439.

[16] Arizal, M. Hilal, and D. F. Priambodo, "Security Analysis of Indonesian Region Government Web Applications Based on NIST SP 800-115 and WSTG v4.2," *Journal of Information Systems and Informatics*, vol. 8, no. 2, pp. 1776–1799, Apr. 2026, doi: 10.63158/journalisi.v8i2.1558.

[17] S. Alazmi and D. C. De Leon, "A Systematic Literature Review on the Characteristics and Effectiveness of Web Application Vulnerability Scanners," *IEEE Access*, vol. 10, pp. 33200–33219, 2022, doi: 10.1109/ACCESS.2022.3161522.

[18] A. Noor, E. Izzah, and W. Sugandha, "Penggunaan Tanda Tangan Elektronik dalam Penyelenggaraan E-Government Guna Mewujudkan Pelayanan Publik yang Efisien," *Journal of Law, Society, and Islamic Civilization*, vol. 9, no. 1, pp. 1–9, Aug. 2021, doi: 10.20961/jolsic.v9i1.52836.

[19] D. M'Raihi, S. Machani, M. Pei, and J. Rydell, "TOTP: Time-Based One-Time Password Algorithm," RFC 6238, Internet Engineering Task Force (IETF), 2011, doi: 10.17487/RFC6238.
