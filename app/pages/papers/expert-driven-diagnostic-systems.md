# Expert-Driven Diagnostic Systems: An Information-Theoretic Alternative to Categorical Classification

**Bram Luiken¹**, [Co-author TBD]²

¹ AI Engineer, NederAI  
² [Institution TBD]

*Correspondence: Bram Luiken, NederAI*

---

## Abstract

We present a diagnostic methodology that bridges clinical expertise with neural network architecture while maintaining complete interpretability. Unlike categorical systems (DSM-5) or black-box machine learning, our system represents diagnostic knowledge as expert-defined weights in a transparent two-layer architecture. The system naturally handles comorbidity, quantifies diagnostic uncertainty through information theory, and provides traceable inference paths. Critically, this is an information-processing framework that remains agnostic to nosological questions about which patterns constitute pathology—making it adaptable to evolving paradigms from medical-model diagnoses to neurodiversity frameworks. The architecture enables intelligent adaptive assessment, modular diagnosis expansion, and bidirectional inference for prototype generation. We demonstrate how this approach addresses fundamental technical limitations of current diagnostic frameworks while remaining practical for clinical implementation.

**Keywords**: diagnostic systems, information theory, interpretable AI, comorbidity, expert systems

---

## 1. Introduction

### 1.1 The Categorical Paradigm Problem

The DSM-5 represents the current gold standard in psychiatric diagnosis, organizing mental health conditions into discrete categorical entities. This approach offers several advantages: standardized terminology, insurance billing codes, and research cohort definitions. However, categorical systems face fundamental challenges:

**Arbitrary thresholds**: A patient with 4 of 5 required symptoms receives no diagnosis; with 5 symptoms, they receive a binary label. This discontinuity contradicts clinical reality where symptom severity exists on continua.

**Forced mutual exclusivity**: The categorical structure discourages multiple diagnoses despite 45-80% of patients meeting criteria for multiple disorders (comorbidity). Clinicians must choose a "primary" diagnosis, losing information.

**Information loss**: A diagnosis collapses rich symptom profiles into single labels. Two patients with the same diagnosis may share minimal symptom overlap (heterogeneity problem).

**Update rigidity**: Revising diagnostic criteria requires multi-year committee processes and produces disruptive categorical shifts rather than incremental refinement.

### 1.2 The Machine Learning Alternative

Modern machine learning offers powerful pattern recognition but introduces different problems for clinical diagnostics:

**Opacity**: Deep neural networks achieve high accuracy but provide no insight into *why* a prediction was made. This "black box" problem is unacceptable when decisions affect patient care and clinicians bear legal responsibility.

**Data hunger**: Supervised learning requires large labeled datasets. In many clinical domains, such data is scarce, expensive, or introduces systematic biases.

**Concept drift**: Models trained on historical data may fail when disease presentation evolves, new conditions emerge, or patient populations shift. Retraining requires new labeled data and validation cycles.

**Non-interpretable representations**: Learned feature embeddings lack semantic meaning. A clinician cannot inspect weights to verify alignment with medical knowledge or identify problematic associations.

### 1.3 Our Approach: Expert-Driven Neural Architecture

We propose a third path that combines the structure of neural networks with the transparency of expert systems:

- **Architecture**: Two-layer matrix multiplication with concatenation
- **Weights**: Directly specified by clinical experts (not learned from data)
- **Activations**: Hyperbolic tangent (layer 1) and signed logarithm (layer 2)
- **Output**: Continuous scores with information-theoretic thresholding
- **Inference**: Bidirectional and completely traceable

This approach offers advantages over both categorical systems and machine learning:

1. **Transparent knowledge representation**: Every weight encodes explicit clinical reasoning
2. **Natural comorbidity handling**: Multiple diagnoses with quantified uncertainty
3. **Incremental refinement**: Experts adjust specific weights without system-wide retraining
4. **No training data required**: System embodies existing clinical expertise
5. **Complete interpretability**: Clinicians can inspect and validate all inference paths
6. **Adaptive assessment**: Gradients guide efficient selection of diagnostic questions
7. **Modular evolution**: New diagnoses and weight sets can be added without architectural changes

### 1.4 Scope and Limitations

**Information processing, not nosology**: This system addresses *how* to map symptom patterns to diagnostic labels, not *which* patterns deserve labels or constitute pathology. The fundamental question—"Is this variation pathological or normal human diversity?"—remains a socio-cultural judgment that precedes system design.

For example, ADHD exists as a diagnosis because experts chose to define it. Whether that label represents genuine pathology, neurodiversity pathologized by institutional demands, or context-dependent impairment is a nosological question our system does not answer. Similarly, adolescent mood swings are not pathologized despite being measurable patterns, reflecting a value judgment about normal development.

This is a **feature, not a limitation**. The architecture is paradigm-agnostic and works equally well for traditional medical-model diagnoses, neurodiversity-informed dimensional profiles, culture-specific syndromes, or Research Domain Criteria (RDoC) dimensional constructs. What changes is the label set and expert weights, not the architecture.

**Why labels remain essential**: Despite legitimate critique of psychiatric nosology, diagnostic labels serve critical pragmatic functions: they enable communication between clinicians, provide targets for treatment selection, facilitate research cohort assembly, justify insurance reimbursement, and help patients conceptualize their experiences. The framework can support whatever label system stakeholders agree upon.

**Symptomatology versus etiology**: This system classifies *symptomatological presentations*, not underlying causes. It identifies patterns in observable phenomena and maps them to diagnostic labels. Even when strong correlations exist between a classification and specific etiological factors (genetic, neurobiological, environmental), inferring causation from classification would be clinically irresponsible. Two patients receiving identical diagnosis scores may have entirely different underlying mechanisms. The system supports differential diagnosis and treatment selection based on presentation patterns, but causal inference requires additional investigation beyond symptom pattern matching.

---

## 2. System Architecture

### 2.1 Mathematical Framework

The system consists of two sequential transformations:

**Layer 1 (Cluster Detection)**:
```
c = tanh(s · W₁)
```
where:
- s ∈ [-1,1]ⁿ: normalized symptom vector (n symptoms)
- W₁ ∈ ℝⁿˣᵐ: expert-defined symptom-to-cluster weights
- c ∈ [-1,1]ᵐ: cluster activations (m clusters)
- tanh: hyperbolic tangent activation

**Layer 2 (Diagnosis Generation)**:
```
d = φ([s, c] · W₂)
```
where:
- [s, c] ∈ [-1,1]ⁿ⁺ᵐ: concatenated symptoms and clusters
- W₂ ∈ ℝ⁽ⁿ⁺ᵐ⁾ˣᵏ: expert-defined weights to diagnoses
- d ∈ ℝᵏ: diagnosis scores (k diagnoses)
- φ: signed logarithmic activation

### 2.2 Input Representation

Symptoms are assessed on a 7-point Likert scale and normalized:

**Likert scale**: 1 (minimal) → 7 (severe)  
**Normalization**: (Likert - 4) / 3 → [-1, 1]  
**Key property**: Likert 4 maps to 0 (no signal)

This creates a *deviation-from-norm* representation where negative values indicate below-typical presentation, zero indicates typical presentation, and positive values indicate above-typical presentation. Clinical decision-making naturally focuses on deviations from baseline rather than absolute measurements.

### 2.3 Activation Functions

**Layer 1 - Hyperbolic tangent**:
```
tanh(u) = (eᵘ - e⁻ᵘ)/(eᵘ + e⁻ᵘ) ∈ [-1, 1]
```

**Design rationale**: Using tanh ensures clusters have the same scale as symptoms (both in [-1, 1]). This scale consistency makes layer 2 weights directly comparable and expert weight specification intuitive. Without scale matching, experts would need to mentally compensate for different input scales when assigning W₂ weights.

Properties:
- Bounded output prevents extreme cluster dominance
- Sign preservation distinguishes above-norm vs. below-norm patterns  
- Smooth and differentiable everywhere
- Saturates gracefully at extremes

**Layer 2 - Signed logarithm**:
```
φ(u) = sign(u) · log(1 + α|u|)
```
with α > 0 controlling compression strength.

Properties:
- Lipschitz continuous: |φ'(u)| = α/(1 + α|u|) ≤ α (bounded gradients, stable)
- Sign preservation essential for distinguishing diagnostic directions
- Logarithmic compression matches clinical perception where "highly likely" and "extremely likely" warrant similar clinical action
- Symmetric treatment of opposing patterns

### 2.4 The Concatenation Strategy

Layer 2 receives both original symptoms *and* computed clusters. This design provides:

**No information bottleneck**: Clusters do not replace symptoms. If clusters fail to capture relevant patterns, diagnoses retain access to raw symptom data.

**Flexible expert reasoning**: For each diagnosis, experts can choose to:
- Reinforce with clusters (cluster + symptom both contribute)
- Contrast with clusters (typical pattern absent + specific symptom present)
- Ignore clusters (direct symptom-to-diagnosis mapping)
- Differentiate (shared symptoms, clusters disambiguate)

**Reusable intermediate concepts**: Clusters represent clinical constructs (e.g., "autonomic dysregulation," "cognitive decline pattern") that appear across multiple diagnoses, reducing redundancy in W₂.

---

## 3. Information-Theoretic Output

### 3.1 Rejecting Softmax Normalization

Traditional machine learning applies softmax to ensure outputs sum to 1. We explicitly reject this:

**Problem 1 - Forced competition**: Softmax creates zero-sum dynamics. In clinical reality, learning a patient has depression doesn't make anxiety less likely—they're positively correlated.

**Problem 2 - Artificial confidence**: Softmax always produces a "confident" distribution even when evidence is weak.

**Problem 3 - Comorbidity suppression**: Softmax structurally biases toward single-diagnosis outputs despite real patients often having multiple co-occurring conditions.

### 3.2 Entropy-Based Thresholding

We use information theory to quantify diagnostic uncertainty:

**Step 1 - Normalize for entropy calculation only**:
```
pᵢ = exp(dᵢ) / Σⱼ exp(dⱼ)
```
This creates a pseudo-probability for measuring uncertainty. We retain original scores d for reporting.

**Step 2 - Compute Shannon entropy**:
```
H = -Σᵢ pᵢ log₂(pᵢ)
```

Interpretation:
- H ≈ 0: One diagnosis dominates (high certainty)
- H ≈ log₂(k): All diagnoses equally likely (maximum uncertainty)
- Intermediate H: Genuine ambiguity or comorbidity

**Step 3 - Threshold and report**:
- Retain diagnosis i if pᵢ > θ (e.g., θ = 0.15) or dᵢ in top-N
- Report: retained diagnoses with original scores dᵢ, global entropy H
- Clinical interpretation: H < 1.5 (relatively certain), H > 2.5 (genuinely ambiguous)

### 3.3 Comparison to DSM

| Aspect | DSM-5 | Our System |
|--------|-------|------------|
| **Comorbidity** | Listed separately, forced hierarchies | Naturally integrated, quantified |
| **Uncertainty** | Not represented | Entropy metric |
| **Threshold** | Fixed criteria counts | Adaptive, information-based |
| **Partial match** | Binary | Continuous scores |
| **Updates** | Requires new edition | Adjust individual weights |

---

## 4. Interpretability and Traceability

### 4.1 Forward Inference - Contribution Decomposition

For any diagnosis dₖ, we can trace exactly which factors contributed:

**Direct symptom contribution**:
```
Δdₖ^(direct) = Σᵢ sᵢ · W₂[i, k]
```

**Cluster-mediated contribution**:
```
Δdₖ^(cluster) = Σⱼ tanh(Σᵢ sᵢ · W₁[i,j]) · W₂[n+j, k]
```

This provides complete audit trails: "Diagnosis X received +2.3 from depressed-mood symptom directly, +1.1 from anhedonia-cluster indirectly."

### 4.2 Reverse Inference - Prototype Generation

The system can run *backwards* to generate typical symptom profiles for each diagnosis:

**Given target diagnosis** (e.g., pure MDD = one-hot vector with MDD=1):
1. Identify which clusters contribute: inspect W₂[n:n+m, MDD]
2. Back-project to symptoms: s_proto ∝ W₁ · W₂_clusters^T
3. Result: prototypical symptom pattern for that diagnosis

This is pure linear algebra—no optimization required. The prototypes evolve automatically as experts refine weights, providing an interactive exploration tool for understanding how diagnostic concepts manifest symptomatically.

### 4.3 Adaptive Assessment - Gradient-Guided Questioning

During assessment with incomplete symptom data, identify which unmeasured symptom would most reduce uncertainty:

**Compute gradients**: ∂dᵢ/∂sⱼ for all unmeasured symptoms j and all diagnoses i

**Score informativeness**: Symptoms with high variance in gradients across diagnoses are most discriminative

**Single forward pass**: All gradients computed simultaneously via automatic differentiation

This is simpler than full Bayesian information gain while remaining effective. The system can dynamically order assessment questions to minimize patient burden while maximizing diagnostic clarity.

### 4.4 Expert Validation

Because weights are manually specified, experts validate the system by:

1. **Direct inspection**: "Does W₁[insomnia, sleep-disturbance-cluster] = 2.1 match clinical experience?"
2. **Synthetic cases**: Input known profiles, verify diagnoses match expectations
3. **Contribution analysis**: For real cases, check if reasoning aligns with clinical logic
4. **Prototype examination**: Generate typical presentations, confirm they're clinically sensible
5. **Interactive exploration**: Adjust weights in real-time, observe immediate output changes

This is impossible with learned systems where weight semantics are opaque.

### 4.5 Comparison to Machine Learning

| Property | Black-Box ML | Our System |
|----------|--------------|------------|
| **Why this diagnosis?** | Unclear | Full contribution decomposition |
| **Verify reasoning?** | No | Yes, inspect weights directly |
| **Fix errors?** | Retrain (costly, opaque) | Adjust specific weights |
| **Bias detection** | Statistical analysis post-hoc | Examine weights directly |
| **Typical presentation?** | Generate samples (may hallucinate) | Backward projection (deterministic) |
| **Next best question?** | Requires separate model | Single gradient computation |

---

## 5. Practical Implementation

### 5.1 Expert Elicitation Process

**Phase 1 - Define symptom ontology**: Experts identify comprehensive symptom list (n symptoms) and assessment methods (Likert scales, measurements).

**Phase 2 - Identify clusters**: Experts specify m clinically meaningful intermediate concepts (e.g., "psychomotor agitation," "executive dysfunction").

**Phase 3 - Specify W₁**: For each symptom-cluster pair, expert assigns weight (typically 0-3 range):
- 0: No relationship
- Positive: Symptom increases cluster activation
- Negative: Symptom decreases cluster (e.g., "energy" → "fatigue cluster")
- Magnitude: Strength of relationship

**Phase 4 - Specify W₂**: For each (symptom/cluster, diagnosis) pair, expert assigns weight. Because tanh normalizes clusters to [-1,1], symptom weights and cluster weights are directly comparable.

**Phase 5 - Tune α**: Select compression parameter for layer 2 based on desired sensitivity (higher α = less compression, more sensitive).

### 5.2 Validation Strategy

**Concurrent validity**: Apply to cases with established diagnoses. Measure top-1 accuracy, top-3 accuracy, and entropy patterns.

**Expert agreement**: Independent experts rate outputs for "clinical plausibility" on 1-5 scale.

**Ablation studies**: 
- Remove clusters (set all cluster activations to zero): does performance degrade?
- Remove specific connections: does failure mode match clinical expectation?

**Comparative benchmarking**: Compare to DSM criteria and ML baselines.

### 5.3 Clinical Workflow Integration

**Assessment**: Clinician or patient completes symptom survey. System can use gradient-guided questioning to prioritize informative symptoms when time-constrained.

**Computation**: System runs in <1ms (two matrix multiplications). Can be client-side (privacy-preserving) or server-based.

**Output presentation**:
- Primary: Ranked diagnoses with scores
- Secondary: Entropy metric ("High certainty" / "Ambiguous presentation")  
- Detailed: Contribution breakdown showing symptom/cluster influences

**Clinical judgment**: Clinician interprets output in context of patient history, external information, cultural factors, and treatment response history. The system augments but does not replace clinical judgment.

### 5.4 Handling Edge Cases

**Missing data**: Set missing symptoms to 0 (no signal). Track coverage and report entropy with caveat if substantial data missing.

**Extreme values**: Input normalization to [-1, 1] prevents extreme symptom dominance. Tanh in layer 1 naturally saturates extreme cluster values. Symptoms bypass layer 1 directly to W₂, so they retain their normalized scale.

**No diagnosis threshold met**: Report "No clear diagnosis, consider dimensional assessment" rather than forcing a label.

**Conflicting patterns**: Entropy naturally increases, signaling ambiguity.

---

## 6. Advantages Over Current Approaches

### 6.1 Versus DSM-5

- **Dimensional representation**: Continuous symptom severity
- **Comorbidity-native**: Multiple diagnoses with quantified uncertainty
- **Subthreshold cases**: Partial criteria yield meaningful scores
- **Continuous evolution**: Update weights as knowledge advances
- **Cultural adaptation**: Tune weights for specific populations
- **Paradigm flexibility**: Same architecture implements alternative frameworks

### 6.2 Versus Machine Learning

- **No training data required**: Embodies existing expertise directly
- **Full transparency**: Every decision traceable
- **Rapid deployment**: Weeks (elicitation) vs. years (data collection, training)
- **Continuous refinement**: Experts adjust weights without retraining
- **Bias auditing**: Inspect weights directly
- **Legal/ethical compliance**: Documented, defensible reasoning
- **Adaptive assessment**: Built-in intelligent questioning
- **Bidirectional**: Generate prototypes, not just classify

### 6.3 Unique Capabilities

**Modular diagnosis expansion**: Adding new diagnoses requires only extending W₂ with new columns and specifying weights. No architectural changes or retraining. Enables rapid integration of emerging conditions (e.g., Long COVID cognitive syndrome, culture-specific constructs).

**Weight set versioning**: Like open-source software, different expert groups can maintain alternative weight sets. A "DSM-alignment" version, "neurodiversity" version, and "RDoC-informed" version can coexist. Weight sets can specialize (child vs. adult, cross-cultural) or merge insights from multiple committees. Full provenance maintained.

**Sparse input robustness**: Gracefully handles partial assessments. With only 30% symptoms measured, produces best-available scores with appropriately elevated entropy. Gradient-guided assessment prioritizes filling critical gaps.

**Interactive exploration**: Functions as educational tool and collaborative refinement interface. Adjust weights in real-time, observe immediate effects. Medical students explore diagnostic reasoning patterns. Expert committees collaboratively refine weights with instant feedback.

**Counterfactual and sensitivity analysis**: Instantly evaluate "what if" scenarios. Identify most discriminative symptoms for differential diagnosis by examining weight differences.

---

## 7. Limitations and Future Directions

### 7.1 Current Limitations

**Expert burden**: Specifying hundreds or thousands of weights is labor-intensive. Requires systematic elicitation and multiple experts.

**Weight uncertainty**: Experts may disagree on magnitudes. Could incorporate multiple weight sets and report consensus + disagreement.

**Validation challenges**: Without ground-truth "correct" diagnoses (philosophical issue in psychiatry), validation relies on expert judgment and patient outcomes.

**Nosological neutrality is not nosological solution**: System implements whatever framework experts provide. Does not resolve debates about pathologization.

### 7.2 Future Directions

**Hierarchical diagnostic structures**: The architecture naturally extends to multi-level taxonomies. A third layer can group diagnoses into syndromes (e.g., "mood disorders," "psychotic disorders"), and a fourth into broader domains. Each level can be refined independently. This supports both specific differential diagnosis and high-level screening. The hierarchy emerges from expert specification of additional weight matrices rather than requiring new architectural principles.

**Temporal extensions**: Extend to time-series for tracking disease progression, treatment response, and episode prediction. The same forward/backward inference principles apply, with recurrent connections or attention mechanisms over temporal symptom trajectories.

**Hybrid learning**: Initialize with expert weights, then fine-tune on data while constraining updates to preserve interpretability. For example, allow only sparse weight adjustments or require that weight signs (positive/negative) remain fixed. This combines expert priors with data-driven refinement.

**Probabilistic weight modeling**: Rather than single point estimates, model weight uncertainty probabilistically. Experts specify ranges or distributions. System reports not just diagnosis scores but confidence intervals reflecting weight uncertainty. Enables principled aggregation of multiple expert opinions.

**Personalized baselines**: Individual patients may have different "typical" baselines (what maps to Likert 4 = 0). Learn patient-specific normalizations from longitudinal data. The deviation-from-norm representation becomes truly personalized.

**Multi-modal integration**: Extend beyond self-report symptoms to include lab values, imaging biomarkers, genetic markers, ecological momentary assessments, and wearable sensor data. Each modality gets its own symptom-like vector. Clusters can integrate across modalities. This moves toward comprehensive biopsychosocial phenotyping.

**Automated prototype refinement**: Use reverse inference to generate prototypical presentations, then ask experts to rate their clinical validity. Iteratively adjust weights to produce increasingly realistic prototypes. This provides an alternative pathway for weight refinement that may be more intuitive than direct numerical specification.

**Causal discovery assistance**: While the system itself cannot infer causation, its transparent structure could support causal discovery workflows. By tracking which weight patterns best predict treatment response or longitudinal outcomes, researchers can generate hypotheses about causal mechanisms for targeted experimental investigation.

---

## 8. Conclusion

We have presented a diagnostic methodology that synthesizes neural network structure with expert knowledge representation and information-theoretic principles. The system addresses fundamental technical limitations of both categorical diagnostic systems (DSM) and black-box machine learning.

The two-layer architecture with concatenation provides a flexible framework where clinical experts directly encode diagnostic reasoning as network weights. Hyperbolic tangent activation ensures scale consistency for intuitive weight specification. Signed logarithmic activation matches clinical intuition about diagnostic certainty. Information-theoretic output quantifies uncertainty without forcing artificial categorization. Bidirectional inference enables both classification and prototype generation.

Most critically, every inference is completely traceable. Clinicians can inspect exactly why the system proposed a diagnosis, explore typical presentations through reverse inference, and receive guidance on informative assessment questions through gradient analysis. This transparency is not merely desirable but essential for clinical acceptance and legal responsibility.

The system is an information-processing framework, not a resolution of nosological debates. It does not determine which patterns constitute pathology versus normal variation—that remains a socio-cultural judgment. However, this paradigm-agnostic design means the architecture adapts to evolving understandings of mental health, from traditional medical diagnoses to neurodiversity frameworks, without fundamental redesign.

The system is not a replacement for clinical judgment but a formalization of diagnostic reasoning that augments expertise, handles complexity systematically, and evolves as medical knowledge and societal values advance. Future work will validate this approach in clinical settings, explore hybrid learning approaches, demonstrate the architecture's flexibility across alternative diagnostic paradigms, and extend to multi-modal and temporal data.

---

## Appendix: Worked Example

**Case**: Patient presents with the following symptoms (Likert → normalized):

- Depressed mood: 6 → +0.67
- Anhedonia: 5 → +0.33  
- Insomnia: 6 → +0.67
- Fatigue: 5 → +0.33
- Concentration difficulty: 5 → +0.33
- Psychomotor agitation: 2 → -0.67

**Layer 1 - Cluster computation** (2 clusters):

W₁ excerpt:
```
                    Depression  Anxiety
Depressed mood         2.0       0.5
Anhedonia              2.5       0.0
Insomnia               1.0       1.5
Fatigue                1.5       0.5
Concentration          1.0       1.5
Psychomotor agitation  0.0       2.0
```

Raw scores:
- Depression: 0.67×2.0 + 0.33×2.5 + 0.67×1.0 + 0.33×1.5 + 0.33×1.0 + (-0.67)×0.0 = 3.62
- Anxiety: 0.67×0.5 + 0.33×0.0 + 0.67×1.5 + 0.33×0.5 + 0.33×1.5 + (-0.67)×2.0 = 0.67

After tanh:
- Depression-cluster: tanh(3.62) ≈ 0.998
- Anxiety-cluster: tanh(0.67) ≈ 0.586

**Layer 2 - Diagnosis computation**:

W₂ excerpt for Major Depressive Disorder:
```
Depressed mood:       1.5
Anhedonia:            2.0
Insomnia:             0.5
Fatigue:              0.8
Concentration:        0.6
Psychomotor agitation: -0.3
Depression-cluster:   2.5
Anxiety-cluster:      0.3
```

Raw MDD score:
```
Direct: 0.67×1.5 + 0.33×2.0 + 0.67×0.5 + 0.33×0.8 + 0.33×0.6 + (-0.67)×(-0.3) = 2.66
Clusters: 0.998×2.5 + 0.586×0.3 = 2.68
Total: 5.34
```

After φ (α=1): sign(5.34) × log(1+5.34) ≈ +1.85

Similarly for other diagnoses...

**Output**:
- Major Depressive Disorder: 1.85
- Generalized Anxiety Disorder: 1.12
- Adjustment Disorder: 0.43

Entropy H = 1.21 (relatively certain, depression-dominant)

**Contribution breakdown for MDD**:
- Direct from symptoms: 2.66
- Via depression-cluster: 2.50
- Via anxiety-cluster: 0.18

**Reverse inference check**: Given target "pure MDD", backward projection yields symptom prototype: high depressed mood, high anhedonia, moderate fatigue/insomnia—matches clinical expectations for typical MDD presentation.

**Adaptive assessment**: If only first 3 symptoms measured, gradients indicate "anhedonia" has highest discriminative value for completing differential diagnosis. System would prioritize that question next.

---

## References

[Note: In publication, include references to:
- DSM-5 critique literature (Insel, Cuthbert on RDoC; Frances on overdiagnosis)
- Nosology debates (Kendell & Jablensky; Hyman on categorical vs dimensional)
- Cultural psychiatry (Kirmayer; debates on pathologizing variation)
- Information theory (Shannon, Cover & Thomas)
- Interpretable ML (Lipton, Rudin, Molnar)
- Clinical comorbidity statistics
- Neural network theory (activation functions, Lipschitz continuity)
- Expert systems in medicine (MYCIN, clinical decision support)
- Neurodiversity perspectives (Walker, Chapman)]
